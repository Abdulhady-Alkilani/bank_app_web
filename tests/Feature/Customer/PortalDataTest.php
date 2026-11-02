<?php

require_once __DIR__.'/Helpers.php';

use App\Contracts\ScoringPipeline;
use App\Enums\CustomerReportStatus;
use App\Enums\LabelSource;
use App\Enums\TransactionStatus;
use App\Livewire\Customer\Activity;
use App\Livewire\Customer\OtpVerify;
use App\Livewire\Customer\Reports;
use App\Livewire\Customer\TransactionList;
use App\Livewire\Customer\TrustedDevices;
use App\Models\AccountEvent;
use App\Models\AuditLog;
use App\Models\CustomerDevice;
use App\Models\CustomerReport;
use App\Models\Label;
use App\Models\OtpChallenge;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\CustomerSecurityNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    portalSeed();
    Mail::fake();
});

it('only shows a customer their own transactions and reports', function () {
    $a = portalCustomer('Alice');
    $b = portalCustomer('Bob');
    $mine = Transaction::factory()->create(['account_id' => $a['account']->id, 'amount' => 111.11]);
    $theirs = Transaction::factory()->create(['account_id' => $b['account']->id, 'amount' => 987.65]);
    CustomerReport::factory()->create(['customer_id' => $b['customer']->id, 'transaction_id' => $theirs->id, 'description' => 'bobs secret report']);

    Livewire::actingAs($a['user'])->test(TransactionList::class)
        ->assertSee('111.11')->assertDontSee('987.65');

    Livewire::actingAs($a['user'])->test(Reports::class)
        ->assertDontSee('bobs secret report')
        ->assertDontSee('987.65');

    // Acting on somebody else's transaction is refused and creates nothing.
    Livewire::actingAs($a['user'])->test(TransactionList::class)->call('deny', $theirs->id)->assertNotFound();
    expect(Label::count())->toBe(0);

    Livewire::actingAs($a['user'])->test(Reports::class)
        ->set('transactionId', (string) $theirs->id)->set('description', 'not mine at all')
        ->call('submit')->assertHasErrors(['transactionId']);
    expect(CustomerReport::where('customer_id', $a['customer']->id)->count())->toBe(0);

    // OTP challenges and accounts of others are not reachable.
    $otp = OtpChallenge::factory()->create(['customer_id' => $b['customer']->id, 'transaction_id' => $theirs->id]);
    Livewire::actingAs($a['user'])->test(OtpVerify::class, ['challenge' => $otp])->assertNotFound();
    $this->actingAs($a['user'])->get(route('portal.otp', $otp))->assertNotFound();
});

it('records a confirmation as a not-fraud customer label', function () {
    ['user' => $user, 'account' => $account] = portalCustomer();
    $tx = Transaction::factory()->create(['account_id' => $account->id]);

    Livewire::actingAs($user)->test(TransactionList::class)->call('confirm', $tx->id)->assertSee(__('customer.feedback.confirmed'));

    $label = Label::firstOrFail();
    expect($label->is_fraud)->toBeFalse()
        ->and($label->source)->toBe(LabelSource::Customer)
        ->and($label->transaction_id)->toBe($tx->id)
        ->and($label->labeled_by)->toBe($user->id)
        ->and(CustomerReport::count())->toBe(0);
});

it('records a denial as a fraud label and opens a customer report', function () {
    ['user' => $user, 'customer' => $customer, 'account' => $account] = portalCustomer();
    $tx = Transaction::factory()->create(['account_id' => $account->id]);

    $list = Livewire::actingAs($user)->test(TransactionList::class)->call('deny', $tx->id)->assertSee(__('customer.feedback.denied'));
    $list->call('deny', $tx->id); // answering twice changes nothing

    $label = Label::firstOrFail();
    expect(Label::count())->toBe(1)
        ->and($label->is_fraud)->toBeTrue()
        ->and($label->source)->toBe(LabelSource::Customer);

    $report = CustomerReport::firstOrFail();
    expect($report->customer_id)->toBe($customer->id)
        ->and($report->transaction_id)->toBe($tx->id)
        ->and($report->status)->toBe(CustomerReportStatus::New);
});

it('files a fraud report from the reports page and lists it with its status', function () {
    ['user' => $user, 'account' => $account] = portalCustomer();
    $tx = Transaction::factory()->create(['account_id' => $account->id, 'amount' => 321.00]);

    Livewire::actingAs($user)->test(Reports::class)
        ->call('submit')->assertHasErrors(['transactionId', 'description'])
        ->set('transactionId', (string) $tx->id)->set('description', 'I never made this transfer')
        ->call('submit')->assertHasNoErrors()
        ->assertSee(__('customer.reports.submitted'))
        ->assertSee('I never made this transfer')
        ->assertSee(CustomerReportStatus::New->label());

    expect(Label::firstOrFail()->is_fraud)->toBeTrue()
        ->and(CustomerReport::firstOrFail()->status)->toBe(CustomerReportStatus::New)
        ->and(AuditLog::where('action', 'customer.report_created')->exists())->toBeTrue();
});

it('lets the customer untrust and remove devices with an audit trail', function () {
    ['user' => $user, 'customer' => $customer, 'device' => $device] = portalCustomer();
    $link = CustomerDevice::where('customer_id', $customer->id)->firstOrFail();

    Livewire::actingAs($user)->test(TrustedDevices::class)
        ->assertSee($device->device_fingerprint)
        ->call('untrust', $link->id)
        ->assertSee(__('customer.devices.untrusted'));
    expect($link->fresh()->is_trusted)->toBeFalse()
        ->and(AuditLog::where('action', 'customer_device.untrusted')->where('user_id', $user->id)->exists())->toBeTrue();

    Livewire::actingAs($user)->test(TrustedDevices::class)->call('remove', $link->id);
    expect(CustomerDevice::count())->toBe(0)
        ->and(AuditLog::where('action', 'customer_device.removed')->exists())->toBeTrue();

    // Somebody else's device link cannot be touched.
    $other = portalCustomer('Other');
    $otherLink = CustomerDevice::where('customer_id', $other['customer']->id)->firstOrFail();
    Livewire::actingAs($user)->test(TrustedDevices::class)->call('untrust', $otherLink->id)->assertNotFound();
    expect($otherLink->fresh()->is_trusted)->toBeTrue();
});

it('lists recent logins and transactions with their statuses', function () {
    ['user' => $user, 'account' => $account, 'device' => $device] = portalCustomer();
    Transaction::factory()->create(['account_id' => $account->id, 'amount' => 456.78, 'status' => TransactionStatus::Held]);
    AccountEvent::factory()->create([
        'account_id' => $account->id, 'device_id' => $device->id, 'event_type' => 'login',
        'ip_address' => '10.10.0.5', 'details' => ['city' => 'Sanaa'],
    ]);
    $stranger = portalCustomer('Stranger');
    AccountEvent::factory()->create(['account_id' => $stranger['account']->id, 'event_type' => 'login', 'ip_address' => '203.0.113.9']);

    Livewire::actingAs($user)->test(Activity::class)
        ->assertSee('456.78')
        ->assertSee(TransactionStatus::Held->label())
        ->assertSee($device->device_fingerprint)
        ->assertSee('10.10.0.5')
        ->assertDontSee('203.0.113.9');
});

it('notifies the customer when a transaction is held or blocked and when the account is frozen', function () {
    ['user' => $user, 'account' => $account] = portalCustomer();
    $tx = Transaction::factory()->create(['account_id' => $account->id, 'amount' => 700]);

    $tx->update(['status' => TransactionStatus::Held]);
    $tx->update(['status' => TransactionStatus::Blocked]);
    $account->update(['status' => 'frozen']);

    $messages = $user->notifications()->pluck('data')->pluck('message')->all();
    expect($user->notifications()->count())->toBe(3)
        ->and($user->notifications()->first()->type)->toBe(CustomerSecurityNotification::class)
        ->and($messages)->toContain(__('customer.notifications.account_frozen', ['account' => $account->account_number]))
        ->and($user->notifications()->latest('created_at')->get()->every(fn ($n) => ! empty($n->data['url'])))->toBeTrue();

    // Completed transactions do not notify.
    $user->notifications()->delete();
    Transaction::factory()->create(['account_id' => $account->id])->update(['status' => TransactionStatus::Completed]);
    expect($user->notifications()->count())->toBe(0);
});

it('records a login event when a customer signs in and never breaks the sign-in', function () {
    ['user' => $user, 'account' => $account] = portalCustomer();

    Auth::login($user);
    $event = AccountEvent::where('account_id', $account->id)->firstOrFail();
    expect($event->event_type->value)->toBe('login')->and($event->risk_score ?? $event->riskScore)->not->toBeNull();

    // A scoring failure (unknown account number) is swallowed.
    $broken = portalCustomer('Broken');
    $this->mock(ScoringPipeline::class)->shouldReceive('process')->andThrow(new RuntimeException('scoring down'));
    Auth::login($broken['user']);
    expect(Auth::check())->toBeTrue();

    // Staff sign-ins are not scored.
    $count = AccountEvent::count();
    Auth::login(User::factory()->analyst()->create());
    expect(AccountEvent::count())->toBe($count);
});
