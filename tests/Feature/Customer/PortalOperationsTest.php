<?php

require_once __DIR__.'/Helpers.php';

use App\Enums\AccountStatus;
use App\Enums\OtpStatus;
use App\Enums\TransactionStatus;
use App\Livewire\Customer\DevicePanel;
use App\Livewire\Customer\Operations;
use App\Livewire\Customer\OtpVerify;
use App\Models\OtpChallenge;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    portalSeed();
    Mail::fake();
});

it('shows the portal pages to a customer', function () {
    ['user' => $user] = portalCustomer();

    foreach (['dashboard', 'accounts', 'transfer', 'history', 'reports', 'devices'] as $page) {
        $this->actingAs($user)->get(route('portal.'.$page))->assertOk();
    }
});

it('keeps the portal closed to staff and guests', function () {
    $this->get(route('portal.dashboard'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->analyst()->create())->get(route('portal.dashboard'))->assertForbidden();
});

it('completes an allowed transfer and debits the balance', function () {
    ['user' => $user, 'account' => $account, 'beneficiary' => $beneficiary] = portalCustomer();

    Livewire::actingAs($user)->test(Operations::class)
        ->set('beneficiaryId', (string) $beneficiary->id)
        ->set('amount', '100')
        ->call('submitTransfer')
        ->assertHasNoErrors()
        ->assertSee(__('customer.ops.allowed.transfer'));

    $tx = Transaction::firstOrFail();
    expect($tx->status)->toBe(TransactionStatus::Completed)
        ->and($tx->channel)->toBe('portal')
        ->and((float) $account->fresh()->balance)->toBe(49900.0);
});

it('rejects transfers above the balance or limit', function () {
    ['user' => $user, 'beneficiary' => $beneficiary] = portalCustomer();

    Livewire::actingAs($user)->test(Operations::class)
        ->set('beneficiaryId', (string) $beneficiary->id)
        ->set('amount', '25000')
        ->call('submitTransfer')
        ->assertHasErrors(['amount']);

    expect(Transaction::count())->toBe(0);
});

it('challenges a risky transfer, redirects to the OTP page and releases it on the right code', function () {
    ['user' => $user, 'account' => $account] = portalCustomer();
    app()['env'] = 'local';

    $panel = Livewire::actingAs($user)->test(DevicePanel::class)->call('useNewDevice');

    $component = Livewire::actingAs($user)->test(Operations::class)
        ->set('beneficiaryAccount', '9000000555')
        ->set('beneficiaryName', 'New Person')
        ->set('amount', '5000')
        ->call('submitTransfer');

    $tx = Transaction::firstOrFail();
    expect($tx->status)->toBe(TransactionStatus::Held);

    $challenge = OtpChallenge::firstOrFail();
    $component->assertRedirect(route('portal.otp', $challenge->id));

    $code = Cache::get('otp:dev:'.$challenge->id);
    expect($code)->not->toBeNull();

    Livewire::actingAs($user)->test(OtpVerify::class, ['challenge' => $challenge])
        ->assertSee($code)
        ->set('code', $code === '000000' ? '111111' : '000000')
        ->call('verify')
        ->assertSee(__('customer.otp.wrong_code', ['left' => 2]))
        ->set('code', $code)
        ->call('verify')
        ->assertSee(__('customer.otp.verified'));

    expect($tx->fresh()->status)->toBe(TransactionStatus::Completed)
        ->and($challenge->fresh()->status)->toBe(OtpStatus::Verified)
        ->and((float) $account->fresh()->balance)->toBe(45000.0);
});

it('fails the OTP after too many wrong codes and rejects the transfer', function () {
    ['user' => $user] = portalCustomer();
    app()['env'] = 'local';

    Livewire::actingAs($user)->test(DevicePanel::class)->call('useNewDevice');
    Livewire::actingAs($user)->test(Operations::class)
        ->set('beneficiaryAccount', '9000000555')->set('beneficiaryName', 'New Person')->set('amount', '5000')
        ->call('submitTransfer');

    $challenge = OtpChallenge::firstOrFail();
    $wrong = Cache::get('otp:dev:'.$challenge->id) === '123456' ? '654321' : '123456';

    $otp = Livewire::actingAs($user)->test(OtpVerify::class, ['challenge' => $challenge]);
    foreach (range(1, 3) as $i) {
        $otp->set('code', $wrong)->call('verify');
    }
    $otp->assertSee(__('customer.otp.failed'));

    expect(Transaction::first()->status)->toBe(TransactionStatus::Rejected);
});

it('blocks a takeover-style transfer, freezes the account and shows the frozen banner', function () {
    ['user' => $user, 'account' => $account] = portalCustomer();

    Livewire::actingAs($user)->test(DevicePanel::class)->call('useNewDevice');

    $ops = Livewire::actingAs($user)->test(Operations::class)
        ->call('setTab', 'phone')
        ->set('newPhone', '+967733000999')
        ->call('submitPhone')
        ->call('setTab', 'transfer')
        ->set('beneficiaryAccount', '9000000777')
        ->set('beneficiaryName', 'Mule')
        ->set('amount', '15000')
        ->call('submitTransfer');

    $ops->assertSee(__('customer.ops.blocked'));

    expect(Transaction::first()->status)->toBe(TransactionStatus::Blocked)
        ->and($account->fresh()->status)->toBe(AccountStatus::Frozen);

    // Further operations are refused and the banner is shown.
    Livewire::actingAs($user)->test(Operations::class)
        ->set('amount', '10')
        ->call('submitTransfer')
        ->assertSee(__('customer.frozen.body'));
    expect(Transaction::count())->toBe(1);
});

it('uses the simulation device in the event it sends', function () {
    ['user' => $user, 'account' => $account] = portalCustomer();

    Livewire::actingAs($user)->test(DevicePanel::class)->call('useNewDevice');
    Livewire::actingAs($user)->test(Operations::class)
        ->call('setTab', 'beneficiary')
        ->set('beneficiaryAccount', '9000000321')->set('beneficiaryName', 'Someone')
        ->call('submitBeneficiary');

    $event = $account->events()->firstOrFail();
    expect($event->device->device_fingerprint)->toStartWith('portal-unknown-')
        ->and($event->ip_address)->toBe('185.220.101.7');
});
