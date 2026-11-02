<?php

require_once __DIR__.'/../Scoring/Helpers.php';

use App\Contracts\ScoringPipeline;
use App\Enums\TransactionStatus;
use App\Jobs\ProcessBankEvent;
use App\Mail\OtpCodeMail;
use App\Models\Account;
use App\Models\AccountEvent;
use App\Models\ApiClient;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Label;
use App\Models\OtpChallenge;
use App\Models\RiskScore;
use App\Models\Transaction;
use App\Services\Otp\OtpService;
use App\Support\ApiSignature;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    scoringSeed();
    Mail::fake();
    $this->client = ApiClient::factory()->create();

    $this->signed = function (string $path, array $body, array $headers = [], ?ApiClient $client = null, ?int $timestamp = null) {
        $client ??= $this->client;
        $json = json_encode($body);

        return $this->call(
            'POST',
            '/api/v1/'.$path,
            [],
            [],
            [],
            collect(array_merge(ApiSignature::headers($client->key_id, $client->secret, $json, $timestamp), ['Accept' => 'application/json'], $headers))
                ->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])
                ->put('CONTENT_TYPE', 'application/json')
                ->all(),
            $json,
        );
    };
});

describe('HMAC authentication', function () {
    it('rejects requests without signature headers', function () {
        $this->postJson('/api/v1/score', [])->assertUnauthorized()->assertJsonPath('code', 'missing_headers');
    });

    it('rejects a bad signature', function () {
        ($this->signed)('score', ['x' => 1], ['X-Signature' => 'deadbeef'])->assertUnauthorized()->assertJsonPath('code', 'invalid_signature');
    });

    it('rejects a body that was tampered with after signing', function () {
        $headers = ApiSignature::headers($this->client->key_id, $this->client->secret, '{"a":1}');

        $this->withHeaders($headers)->postJson('/api/v1/score', ['a' => 2])->assertUnauthorized()->assertJsonPath('code', 'invalid_signature');
    });

    it('rejects stale and future timestamps', function () {
        ($this->signed)('score', ['x' => 1], [], null, time() - 400)->assertUnauthorized()->assertJsonPath('code', 'timestamp_out_of_range');
        ($this->signed)('score', ['x' => 1], [], null, time() + 400)->assertUnauthorized()->assertJsonPath('code', 'timestamp_out_of_range');
    });

    it('rejects unknown, inactive and revoked clients', function () {
        $unknown = new ApiClient(['key_id' => 'nope', 'secret' => 'x']);
        ($this->signed)('score', [], [], $unknown)->assertUnauthorized()->assertJsonPath('code', 'invalid_client');

        $this->client->update(['is_active' => false]);
        ($this->signed)('score', [])->assertUnauthorized()->assertJsonPath('code', 'invalid_client');

        $this->client->update(['is_active' => true, 'revoked_at' => now()]);
        ($this->signed)('score', [])->assertUnauthorized()->assertJsonPath('code', 'invalid_client');
    });

    it('accepts a valid signature and records last_used_at', function () {
        expect($this->client->last_used_at)->toBeNull();

        ($this->signed)('score', [])->assertStatus(422);

        expect($this->client->fresh()->last_used_at)->not->toBeNull();
    });

    it('rate limits per API client', function () {
        config(['fraud.api_rate_limit' => 2]);
        ($this->signed)('score', [])->assertStatus(422);
        ($this->signed)('score', [])->assertStatus(422);
        ($this->signed)('score', [])->assertStatus(429);
    });
});

describe('POST /score', function () {
    it('scores synchronously and returns the decision payload', function () {
        ['account' => $account, 'device' => $device] = scoringCustomer();

        $response = ($this->signed)('score', scoringTransfer($account, $device, 100))->assertOk();

        $response->assertJsonStructure(['subject_type', 'subject_id', 'risk_score_id', 'score', 'decision', 'reasons', 'otp_challenge_id', 'latency_ms'])
            ->assertJsonPath('subject_type', 'transaction')
            ->assertJsonPath('decision', 'allow');
        expect(RiskScore::find($response->json('risk_score_id'))->score)->toBe($response->json('score'));
    });

    it('returns an otp challenge id on challenge and honours the Idempotency-Key header', function () {
        ['account' => $account, 'device' => $device] = scoringCustomer();
        Beneficiary::factory()->create(['account_id' => $account->id, 'beneficiary_account' => '7000000001', 'added_at' => now()->subDays(3), 'created_at' => now()->subDays(60)]);
        $payload = scoringTransfer($account, $device, 100, '7000000001');
        app(ScoringPipeline::class)->process(['event_type' => 'change_phone', 'account_number' => $account->account_number, 'device_fingerprint' => $device->device_fingerprint, 'event_time' => now()->subMinutes(5)->toDateTimeString()]);
        $payload['amount'] = 14000;

        $first = ($this->signed)('score', $payload, ['Idempotency-Key' => 'k-1'])->assertOk();
        $second = ($this->signed)('score', $payload, ['Idempotency-Key' => 'k-1'])->assertOk();

        expect($first->json('decision'))->toBe('challenge')
            ->and($first->json('otp_challenge_id'))->not->toBeNull()
            ->and($second->json('risk_score_id'))->toBe($first->json('risk_score_id'))
            ->and($second->json('otp_challenge_id'))->toBe($first->json('otp_challenge_id'))
            ->and(Transaction::count())->toBe(1);
    });

    it('validates the payload', function () {
        ($this->signed)('score', ['event_type' => 'transfer', 'account_number' => 'missing'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['account_number', 'amount', 'beneficiary_account']);

        ($this->signed)('score', ['event_type' => 'bogus', 'account_number' => 'x'])->assertJsonValidationErrors(['event_type']);
    });
});

describe('POST /events', function () {
    it('accepts asynchronously with 202 and queues ProcessBankEvent', function () {
        Bus::fake();
        ['account' => $account, 'device' => $device] = scoringCustomer();

        $response = ($this->signed)('events', scoringTransfer($account, $device, 100), ['Idempotency-Key' => 'evt-9'])->assertStatus(202);

        $response->assertJsonPath('status', 'accepted')->assertJsonPath('idempotency_key', 'evt-9');
        Bus::assertDispatched(ProcessBankEvent::class, fn ($job) => $job->payload['idempotency_key'] === 'evt-9');
    });

    it('processes the event through the pipeline on the sync queue, once', function () {
        ['account' => $account, 'device' => $device] = scoringCustomer();
        $payload = scoringTransfer($account, $device, 100);

        ($this->signed)('events', $payload, ['Idempotency-Key' => 'evt-1'])->assertStatus(202);
        ($this->signed)('events', $payload, ['Idempotency-Key' => 'evt-1'])->assertStatus(202);

        expect(Transaction::count())->toBe(1)
            ->and(RiskScore::count())->toBe(1)
            ->and(Transaction::first()->status)->toBe(TransactionStatus::Completed);
    });

    it('stores non transfer events as account events', function () {
        ['account' => $account, 'device' => $device] = scoringCustomer();

        ($this->signed)('events', ['event_type' => 'login', 'account_number' => $account->account_number, 'device_fingerprint' => $device->device_fingerprint])->assertStatus(202);

        expect(AccountEvent::count())->toBe(1)->and(Transaction::count())->toBe(0);
    });
});

describe('POST /otp/verify', function () {
    it('verifies a challenge', function () {
        $customer = Customer::factory()->create();
        $tx = Transaction::factory()->held()->create();
        $code = null;
        $challenge = app(OtpService::class)->issue($customer, $tx);
        Mail::assertSent(OtpCodeMail::class, function ($m) use (&$code) {
            $code = $m->code;

            return true;
        });

        $wrong = $code === '123456' ? '654321' : '123456';
        ($this->signed)('otp/verify', ['otp_challenge_id' => $challenge->id, 'code' => $wrong])
            ->assertOk()->assertJsonPath('verified', false)->assertJsonPath('remaining_attempts', 2);

        ($this->signed)('otp/verify', ['otp_challenge_id' => $challenge->id, 'code' => $code])
            ->assertOk()->assertJsonPath('verified', true)->assertJsonPath('transaction_status', 'completed');

        ($this->signed)('otp/verify', ['otp_challenge_id' => 9999, 'code' => '123456'])->assertStatus(422);
    });
});

describe('POST /verification-results', function () {
    it('turns bank outcomes into labels', function () {
        $tx = Transaction::factory()->create();

        ($this->signed)('verification-results', ['type' => 'transaction', 'reference_id' => $tx->id, 'outcome' => 'fraud'])
            ->assertCreated()->assertJsonPath('is_fraud', true)->assertJsonPath('source', 'bank');

        expect(Label::where('transaction_id', $tx->id)->first()->is_fraud)->toBeTrue();

        $challenge = OtpChallenge::factory()->create(['transaction_id' => $tx->id]);
        ($this->signed)('verification-results', ['type' => 'otp', 'reference_id' => $challenge->id, 'outcome' => 'verified'])
            ->assertCreated()->assertJsonPath('is_fraud', false)->assertJsonPath('source', 'customer');

        ($this->signed)('verification-results', ['type' => 'otp', 'reference_id' => $challenge->id, 'outcome' => 'fraud'])->assertStatus(422);
    });
});

describe('POST /sync', function () {
    it('upserts customers, accounts and devices idempotently', function () {
        $body = ['customers' => [[
            'name' => 'Ahmed Ali', 'email' => 'ahmed@bank.test', 'phone' => '+967711000001', 'avg_balance' => 1000,
            'accounts' => [['account_number' => 'ACC-1', 'balance' => 5000, 'transfer_limit' => 20000]],
            'devices' => [['device_fingerprint' => 'fp-sync-1', 'device_type' => 'android']],
        ]]];

        ($this->signed)('sync', $body)->assertOk()->assertJsonPath('customers', 1)->assertJsonPath('accounts', 1)->assertJsonPath('devices', 1);
        $body['customers'][0]['avg_balance'] = 2000;
        ($this->signed)('sync', $body)->assertOk();

        expect(Customer::where('email', 'ahmed@bank.test')->count())->toBe(1)
            ->and(Customer::first()->avg_balance)->toBe('2000.00')
            ->and(Account::where('account_number', 'ACC-1')->count())->toBe(1)
            ->and(CustomerDevice::first()->is_trusted)->toBeTrue();

        ($this->signed)('sync', ['customers' => []])->assertStatus(422);
    });
});

it('creates api clients from the artisan command and audits it', function () {
    $this->artisan('api-client:create', ['name' => 'Simulator'])
        ->expectsOutputToContain('key_id: key_')
        ->expectsOutputToContain('secret: ')
        ->assertSuccessful();

    expect(ApiClient::where('name', 'Simulator')->first())->not->toBeNull()
        ->and(AuditLog::where('action', 'api_client.created')->exists())->toBeTrue();
});
