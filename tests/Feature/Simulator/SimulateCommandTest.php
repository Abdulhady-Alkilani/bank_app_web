<?php

use App\Enums\AccountStatus;
use App\Enums\Decision;
use App\Models\Account;
use App\Models\AccountEvent;
use App\Models\Alert;
use App\Models\ApiClient;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Label;
use App\Models\RiskScore;
use App\Models\Transaction;
use App\Services\Simulation\SimulationReset;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function simScoresByKey(string $prefix): array
{
    return RiskScore::query()
        ->with(['transaction', 'event'])
        ->get()
        ->mapWithKeys(fn (RiskScore $r) => [($r->transaction ?? $r->event)?->idempotency_key => $r])
        ->filter(fn ($r, $key) => str_starts_with((string) $key, $prefix))
        ->sortKeys()
        ->all();
}

it('replays the Ahmed takeover with rising scores that end in a block, a freeze and an alert', function () {
    $this->artisan('bank:simulate', ['--scenario' => 'ahmed', '--reset' => true])->assertSuccessful();

    $scores = simScoresByKey('sim-ahmed-');
    // Main chain: normal login, phone change, login, raise limit, add beneficiary, transfer.
    $chain = collect(['sim-ahmed-001', 'sim-ahmed-002', 'sim-ahmed-003', 'sim-ahmed-004', 'sim-ahmed-005', 'sim-ahmed-006'])
        ->map(fn ($key) => $scores[$key]->score)->all();

    expect($chain)->toBe(collect($chain)->sort()->unique()->values()->all())
        ->and($chain[0])->toBeLessThan(40)
        ->and($scores['sim-ahmed-006']->decision)->toBe(Decision::Block)
        ->and($scores['sim-ahmed-006']->score)->toBeGreaterThanOrEqual(75);

    $transfer = Transaction::where('idempotency_key', 'sim-ahmed-006')->firstOrFail();
    expect((float) $transfer->amount)->toBe(14500.0)
        ->and(Account::where('account_number', '1000000001')->first()->status)->toBe(AccountStatus::Frozen)
        ->and(Alert::count())->toBeGreaterThan(0);

    // The mule received money from 9 distinct accounts (Ahmed + 8 victims) and D77 was seen on 13 accounts.
    $mule = Account::where('account_number', '2000000999')->firstOrFail();
    expect($mule->created_at->diffInDays(today()->setTime(21, 0), true))->toBeGreaterThanOrEqual(5)
        ->and(Transaction::query()->whereHas('beneficiary', fn ($q) => $q->where('beneficiary_account', '2000000999'))->distinct()->count('account_id'))->toBe(9)
        ->and(Device::where('device_fingerprint', 'sim-ahmed-D77')->firstOrFail()->customers()->count())->toBe(13);

    // Ahmed's customer gets in-app notifications about the held/blocked transfer and the frozen account.
    $notifications = Customer::where('email', 'ahmed@bank.test')->first()->user->notifications;
    expect($notifications->pluck('data.message')->implode(' '))->toContain('1000000001');
});

it('is repeatable: reset restores Ahmed and removes the previous scenario data', function () {
    $this->artisan('bank:simulate', ['--scenario' => 'ahmed', '--reset' => true])->assertSuccessful();
    $first = Transaction::where('idempotency_key', 'like', 'sim-ahmed-%')->count();

    app(SimulationReset::class)->reset('ahmed');

    $account = Account::where('account_number', '1000000001')->first();
    expect($account->status)->toBe(AccountStatus::Active)
        ->and(Transaction::where('idempotency_key', 'like', 'sim-ahmed-%')->count())->toBe(0)
        ->and(AccountEvent::where('idempotency_key', 'like', 'sim-ahmed-%')->count())->toBe(0)
        ->and(Customer::where('email', 'like', '%.ahmed@sim.bank.test')->count())->toBe(0)
        ->and((float) $account->balance)->toBe(25000.0);

    $this->artisan('bank:simulate', ['--scenario' => 'ahmed'])->assertSuccessful();
    expect(Transaction::where('idempotency_key', 'like', 'sim-ahmed-%')->count())->toBe($first);
});

it('generates normal traffic with mixed fraud patterns and labels', function () {
    $this->artisan('bank:simulate', ['--scenario' => 'normal', '--count' => 60, '--fraud-rate' => 0.1, '--days' => 5])
        ->assertSuccessful();

    $events = Transaction::where('idempotency_key', 'like', 'sim-normal-%')->count()
        + AccountEvent::where('idempotency_key', 'like', 'sim-normal-%')->count();

    expect($events)->toBeGreaterThanOrEqual(40)
        ->and(RiskScore::count())->toBe($events)
        ->and(Label::where('is_fraud', true)->count())->toBeGreaterThan(0)
        ->and(RiskScore::where('decision', '!=', 'allow')->count())->toBeGreaterThan(0);
});

it('runs the mule and shared-device scenarios', function (string $scenario) {
    $this->artisan('bank:simulate', ['--scenario' => $scenario, '--count' => 20])->assertSuccessful();

    expect(RiskScore::count())->toBeGreaterThanOrEqual(6)
        ->and(RiskScore::where('decision', '!=', 'allow')->count())->toBeGreaterThan(0);
})->with(['mule', 'shared-device']);

it('rejects unknown scenarios and modes', function () {
    $this->artisan('bank:simulate', ['--scenario' => 'nope'])->assertExitCode(2);
    $this->artisan('bank:simulate', ['--via' => 'carrier-pigeon'])->assertExitCode(2);
});

it('can send the scenario through the signed HTTP API', function () {
    Http::fake(['*/api/v1/score' => Http::response([
        'subject_type' => 'event', 'subject_id' => 1, 'risk_score_id' => 1, 'score' => 12,
        'decision' => 'allow', 'reasons' => [], 'otp_challenge_id' => null, 'latency_ms' => 5,
    ])]);

    $this->artisan('bank:simulate', ['--scenario' => 'mule', '--count' => 10, '--via' => 'http'])->assertSuccessful();

    expect(ApiClient::where('name', 'Simulator')->count())->toBe(1);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/v1/score')
        && $request->hasHeader('X-Api-Key') && $request->hasHeader('X-Signature') && $request->hasHeader('X-Timestamp'));
});
