<?php

use App\Enums\AccountStatus;
use App\Livewire\Analyst\AccountNetwork;
use App\Livewire\Analyst\CustomerShow;
use App\Livewire\Analyst\Search;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Device;
use App\Models\InvestigationCase;
use App\Models\User;
use App\Services\Investigation\CaseService;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

it('exports a case as an Arabic RTL PDF and audits it', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    $case = app(CaseService::class)->openFromAlert($alert, $analyst);
    app(CaseService::class)->addNote($case, $analyst, 'ملاحظة تجريبية');

    $response = $this->actingAs($analyst)->get(route('analyst.cases.pdf', $case));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and(AuditLog::where('action', 'case.report_exported')->count())->toBe(1);
});

it('does not let other analysts export a case assigned elsewhere', function () {
    $owner = User::factory()->analyst()->create();
    $case = InvestigationCase::factory()->create(['assigned_to' => $owner->id]);

    $this->actingAs(User::factory()->analyst()->create())->get(route('analyst.cases.pdf', $case))->assertForbidden();
});

it('renders every analyst page', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert(['assigned_to' => $analyst->id]);
    $account = $alert->riskScore->transaction->account;
    $case = app(CaseService::class)->openFromAlert($alert, $analyst);

    $this->actingAs($analyst);

    foreach ([
        route('analyst.dashboard'),
        route('analyst.alerts'),
        route('analyst.alerts.show', $alert),
        route('analyst.customers.show', ['customer' => $account->customer, 'alert' => $alert->id]),
        route('analyst.accounts.network', $account),
        route('analyst.cases'),
        route('analyst.cases.show', $case),
        route('analyst.search', ['q' => $account->account_number]),
        route('analyst.assistant'),
        route('analyst.rule-suggestions'),
    ] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get(route('analyst.dashboard'))->assertSee('notification', false);
});

it('masks customer PII until it is revealed with an audit entry', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    $customer = $alert->riskScore->transaction->account->customer;
    $customer->update(['phone' => '+967712345678', 'email' => 'someone@example.com']);

    $component = Livewire::actingAs($analyst)->test(CustomerShow::class, ['customer' => $customer])
        ->assertDontSee('+967712345678')
        ->assertDontSee('someone@example.com');

    $component->call('reveal')->assertSee('+967712345678')->assertSee('someone@example.com');

    expect(AuditLog::where('action', 'customer.pii_revealed')->where('entity_id', $customer->id)->count())->toBe(1);
});

it('searches by account, device, ip, beneficiary and transaction id', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    $tx = $alert->riskScore->transaction;
    $device = Device::factory()->create(['device_fingerprint' => 'fp-unique-device-123']);
    $beneficiary = Beneficiary::factory()->create(['account_id' => $tx->account_id, 'beneficiary_account' => '7771234567']);
    $tx->update(['device_id' => $device->id, 'ip_address' => '203.0.113.9']);

    $search = fn (string $q) => Livewire::actingAs($analyst)->test(Search::class)->set('q', $q);

    $search($tx->account->account_number)->assertSee($tx->account->account_number);
    $search('fp-unique-device')->assertSee('fp-unique-device-123')->assertSee($tx->account->account_number);
    $search('203.0.113')->assertSee('203.0.113.9')->assertSee($tx->account->account_number);
    $search('7771234567')->assertSee('7771234567');
    $search((string) $tx->id)->assertSee(route('analyst.alerts.show', $alert), false);
    $search('nothing-like-this')->assertSee(__('analyst.search.none'));
});

it('plots the network graph payload and clamps the depth', function () {
    $analyst = User::factory()->analyst()->create();
    $account = makeAnalystAlert()->riskScore->transaction->account;
    $account->update(['status' => AccountStatus::Frozen]);

    Livewire::actingAs($analyst)->test(AccountNetwork::class, ['account' => $account])
        ->set('depth', 9)
        ->assertSet('depth', 3)
        ->assertDispatched('network-updated')
        ->assertViewHas('elements', fn ($e) => count($e['nodes']) >= 1);
});
