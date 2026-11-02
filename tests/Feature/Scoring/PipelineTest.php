<?php

require_once __DIR__.'/Helpers.php';

use App\Contracts\ScoringPipeline;
use App\Enums\AccountStatus;
use App\Enums\Decision;
use App\Enums\ModelStatus;
use App\Enums\OtpStatus;
use App\Enums\Priority;
use App\Enums\TransactionStatus;
use App\Enums\WatchlistEntity;
use App\Mail\OtpCodeMail;
use App\Models\Account;
use App\Models\Alert;
use App\Models\Beneficiary;
use App\Models\MlModel;
use App\Models\ModelRun;
use App\Models\OtpChallenge;
use App\Models\RiskScore;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Watchlist;
use App\Services\Scoring\ModelRegistry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    scoringSeed();
    Mail::fake();
});

it('allows a normal transfer and completes it', function () {
    ['account' => $account, 'device' => $device] = scoringCustomer();

    $result = app(ScoringPipeline::class)->process(scoringTransfer($account, $device, 100));

    expect($result->decision)->toBe(Decision::Allow)
        ->and($result->score)->toBeLessThan(40)
        ->and($result->subject->status)->toBe(TransactionStatus::Completed)
        ->and($result->otpChallengeId)->toBeNull()
        ->and(Alert::count())->toBe(0);

    $risk = RiskScore::first();
    expect($risk->model_run_id)->toBe(ModelRun::first()->id)
        ->and($risk->used_fallback)->toBeFalse()
        ->and($risk->features)->toHaveKey('amount_to_avg_ratio')
        ->and($risk->latency_ms)->toBeGreaterThanOrEqual(0);
});

it('challenges a large transfer right after a phone change with an OTP and an alert', function () {
    $analyst = User::factory()->analyst()->create();
    ['account' => $account, 'device' => $device, 'customer' => $customer] = scoringCustomer();
    Beneficiary::factory()->create(['account_id' => $account->id, 'beneficiary_account' => '7000000001', 'added_at' => now()->subDays(2), 'created_at' => now()->subDays(40)]);

    $pipeline = app(ScoringPipeline::class);
    $pipeline->process(['event_type' => 'change_phone', 'account_number' => $account->account_number, 'device_fingerprint' => $device->device_fingerprint, 'new_phone' => '+967711111111', 'event_time' => now()->subMinutes(30)->toDateTimeString()]);

    $result = $pipeline->process(scoringTransfer($account, $device, 12000, '7000000001'));

    expect($result->decision)->toBe(Decision::Challenge)
        ->and($result->subject->status)->toBe(TransactionStatus::Held)
        ->and($result->otpChallengeId)->not->toBeNull()
        ->and(OtpChallenge::find($result->otpChallengeId)->status)->toBe(OtpStatus::Pending)
        ->and($customer->fresh()->phone)->toBe('+967711111111')
        ->and(collect($result->reasons)->pluck('source')->unique()->sort()->values()->all())->toBe(['model', 'rule']);

    Mail::assertSent(OtpCodeMail::class, 1);
    $alert = Alert::first();
    expect($alert)->not->toBeNull()
        ->and($alert->assigned_to)->toBe($analyst->id)
        ->and($account->fresh()->status)->toBe(AccountStatus::Active);
});

it('blocks the Ahmed takeover scenario: new device, phone change, 18x amount', function () {
    $analyst = User::factory()->analyst()->create();
    Notification::fake();
    ['account' => $account] = scoringCustomer();
    $pipeline = app(ScoringPipeline::class);
    $fp = 'attacker-device-1';

    $login = $pipeline->process(['event_type' => 'login', 'account_number' => $account->account_number, 'device_fingerprint' => $fp, 'city' => 'Aden', 'ip_address' => '41.1.1.1']);
    $phone = $pipeline->process(['event_type' => 'change_phone', 'account_number' => $account->account_number, 'device_fingerprint' => $fp, 'city' => 'Aden', 'new_phone' => '+967733333333']);
    $beneficiary = $pipeline->process(['event_type' => 'add_beneficiary', 'account_number' => $account->account_number, 'device_fingerprint' => $fp, 'city' => 'Aden', 'beneficiary_account' => '9990001111', 'beneficiary_name' => 'Mule']);
    $transfer = $pipeline->process(['event_type' => 'transfer', 'account_number' => $account->account_number, 'device_fingerprint' => $fp, 'city' => 'Aden', 'amount' => 18000, 'beneficiary_account' => '9990001111']);

    // Score climbs through the attack and ends in a block.
    expect($login->decision)->toBe(Decision::Allow)
        ->and($transfer->score)->toBeGreaterThan($login->score)
        ->and($transfer->score)->toBeGreaterThanOrEqual(75)
        ->and($transfer->decision)->toBe(Decision::Block)
        ->and($transfer->subject->status)->toBe(TransactionStatus::Blocked)
        ->and($account->fresh()->status)->toBe(AccountStatus::Frozen)
        ->and($phone->decision)->toBe(Decision::Allow)
        ->and($beneficiary->decision)->not->toBeNull();

    $alert = Alert::where('risk_score_id', $transfer->riskScore->id)->first();
    expect($alert->priority)->toBe(Priority::Critical)
        ->and($alert->assigned_to)->toBe($analyst->id)
        ->and($alert->case_id)->not->toBeNull();
});

it('blocks immediately on a blacklist hit', function () {
    ['account' => $account, 'device' => $device] = scoringCustomer();
    Watchlist::factory()->create(['entity_type' => WatchlistEntity::Beneficiary, 'entity_value' => '6660001']);

    $result = app(ScoringPipeline::class)->process(scoringTransfer($account, $device, 10, '6660001'));

    expect($result->score)->toBe(100)->and($result->decision)->toBe(Decision::Block);
});

it('falls back to rules only when the HTTP AI service fails', function (string $mode) {
    config(['fraud.ai.driver' => 'http', 'fraud.ai.url' => 'http://ai.test']);
    $mode === 'http 500'
        ? Http::fake(['*' => Http::response(['error' => 'boom'], 500)])
        : Http::fake(fn () => throw new ConnectionException('timeout'));
    ['account' => $account, 'device' => $device] = scoringCustomer();
    Beneficiary::factory()->create(['account_id' => $account->id, 'beneficiary_account' => '7000000001', 'added_at' => now()->subDays(2), 'created_at' => now()->subDays(40)]);
    $pipeline = app(ScoringPipeline::class);
    $pipeline->process(['event_type' => 'change_phone', 'account_number' => $account->account_number, 'device_fingerprint' => $device->device_fingerprint, 'event_time' => now()->subMinutes(10)->toDateTimeString()]);

    $result = $pipeline->process(scoringTransfer($account, $device, 15000, '7000000001'));

    expect($result->riskScore->used_fallback)->toBeTrue()
        ->and($result->riskScore->model_probability)->toBeNull()
        ->and($result->riskScore->model_run_id)->toBeNull()
        ->and($result->score)->toBe($result->riskScore->rule_score)
        ->and($result->decision)->toBe(Decision::Challenge);
})->with([
    'http 500',
    'connection error',
]);

it('uses the HTTP model probability when the service answers', function () {
    config(['fraud.ai.driver' => 'http', 'fraud.ai.url' => 'http://ai.test', 'fraud.ai.token' => 'tok']);
    Http::fake(['ai.test/v1/score' => Http::response([
        'probability' => 0.8, 'model_name' => 'xgb', 'model_version' => '2.0',
        'top_features' => [['name' => 'is_new_device', 'value' => 1, 'impact' => 0.4]], 'latency_ms' => 12,
    ])]);
    ['account' => $account, 'device' => $device] = scoringCustomer();

    $result = app(ScoringPipeline::class)->process(scoringTransfer($account, $device, 100));

    expect((float) $result->riskScore->model_probability)->toBe(0.8)
        ->and($result->riskScore->used_fallback)->toBeFalse()
        ->and($result->score)->toBe(48);
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer tok') && isset($r['features']['amount_to_avg_ratio']));
});

it('links the risk score to the registered run of the model that answered, not the champion', function () {
    config(['fraud.ai.driver' => 'http', 'fraud.ai.url' => 'http://ai.test']);
    $shadowRun = ModelRun::factory()->create([
        'model_id' => MlModel::factory()->create(['model_name' => 'xgb', 'status' => ModelStatus::Shadow])->id,
        'version_number' => '2.0',
    ]);
    ModelRun::factory()->create(['model_id' => MlModel::factory()->create(['model_name' => 'other'])->id, 'version_number' => '2.0']);
    Http::fake(['ai.test/v1/score' => Http::response(['probability' => 0.1, 'model_name' => 'xgb', 'model_version' => '2.0'])]);
    ['account' => $account, 'device' => $device] = scoringCustomer();

    $result = app(ScoringPipeline::class)->process(scoringTransfer($account, $device, 100));

    expect($result->riskScore->model_run_id)->toBe($shadowRun->id)
        ->and($result->riskScore->model_run_id)->not->toBe(app(ModelRegistry::class)->championRun()->id);
});

it('leaves the model run empty when the answering model version is not registered', function () {
    config(['fraud.ai.driver' => 'http', 'fraud.ai.url' => 'http://ai.test']);
    Http::fake(['ai.test/v1/score' => Http::response(['probability' => 0.1, 'model_name' => 'unknown', 'model_version' => '9.9'])]);
    ['account' => $account, 'device' => $device] = scoringCustomer();

    $result = app(ScoringPipeline::class)->process(scoringTransfer($account, $device, 100));

    expect($result->riskScore->model_run_id)->toBeNull()
        ->and($result->riskScore->used_fallback)->toBeFalse();
});

it('is idempotent: the same key returns the stored score without a duplicate', function () {
    ['account' => $account, 'device' => $device] = scoringCustomer();
    $payload = scoringTransfer($account, $device, 100, overrides: ['idempotency_key' => 'abc-123']);
    $pipeline = app(ScoringPipeline::class);

    $first = $pipeline->process($payload);
    $second = $pipeline->process($payload);

    expect($second->riskScore->id)->toBe($first->riskScore->id)
        ->and($second->subject->id)->toBe($first->subject->id)
        ->and(Transaction::count())->toBe(1)
        ->and(RiskScore::count())->toBe(1);
});

it('rejects an unknown account with a validation error', function () {
    app(ScoringPipeline::class)->process(['event_type' => 'login', 'account_number' => 'nope']);
})->throws(ValidationException::class);

it('opens an account and applies side effects', function () {
    ['customer' => $customer, 'account' => $account] = scoringCustomer();
    $pipeline = app(ScoringPipeline::class);

    $pipeline->process(['event_type' => 'open_account', 'account_number' => '5550001', 'customer_id' => $customer->id]);
    $pipeline->process(['event_type' => 'add_beneficiary', 'account_number' => $account->account_number, 'beneficiary_account' => '1234', 'beneficiary_name' => 'Sara']);

    expect(Account::where('account_number', '5550001')->first()->transfer_limit)->toBe('20000.00')
        ->and(Beneficiary::where('beneficiary_account', '1234')->exists())->toBeTrue();
});
