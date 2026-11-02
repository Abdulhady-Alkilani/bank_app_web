<?php

require_once __DIR__.'/Helpers.php';

use App\Enums\WatchlistEntity;
use App\Enums\WatchlistType;
use App\Models\Account;
use App\Models\Beneficiary;
use App\Models\Device;
use App\Models\Transaction;
use App\Models\Watchlist;
use App\Services\Ingestion\EventIngestor;
use App\Services\Scoring\FeatureService;

function featuresFor(array $payload): array
{
    return app(FeatureService::class)->compute(app(EventIngestor::class)->ingest($payload));
}

it('computes amount ratio, hour and baseline flags for a normal transfer', function () {
    ['account' => $account, 'device' => $device] = scoringCustomer();

    $f = featuresFor(scoringTransfer($account, $device, 500, overrides: ['event_time' => '2026-03-10 14:30:00']));

    expect($f['amount'])->toBe(500.0)
        ->and($f['amount_to_avg_ratio'])->toBe(0.5)
        ->and($f['is_new_device'])->toBe(0)
        ->and($f['location_changed'])->toBe(0)
        ->and($f['hour_of_day'])->toBe(14)
        ->and($f['minutes_since_phone_change'])->toBeNull()
        ->and($f['beneficiary_is_new'])->toBe(1)
        ->and($f['beneficiary_account_age_days'])->toBe(0)
        ->and($f['txn_count_1h'])->toBe(1)
        ->and($f['blacklist_hit'])->toBe(0);
});

it('flags a new device in a new city and counts logins from new devices', function () {
    ['account' => $account] = scoringCustomer();

    $payload = ['event_type' => 'login', 'account_number' => $account->account_number, 'device_fingerprint' => 'new-fp-1', 'city' => 'Aden'];
    featuresFor($payload);
    $f = featuresFor($payload);

    expect($f['is_new_device'])->toBe(1)
        ->and($f['location_changed'])->toBe(1)
        ->and($f['logins_new_device_1h'])->toBe(2);
});

it('measures minutes since the phone change from events and the device sim date', function () {
    ['account' => $account, 'device' => $device] = scoringCustomer();
    $base = now()->subMinutes(45);

    featuresFor(['event_type' => 'change_phone', 'account_number' => $account->account_number, 'device_fingerprint' => $device->device_fingerprint, 'new_phone' => '+967700000000', 'event_time' => $base->toDateTimeString()]);
    $f = featuresFor(scoringTransfer($account, $device, 100, overrides: ['event_time' => now()->toDateTimeString()]));

    expect($f['minutes_since_phone_change'])->toBeBetween(44, 46)
        ->and($device->fresh()->sim_changed_at)->not->toBeNull();
});

it('reports beneficiary age, recency and fan-out', function () {
    ['account' => $account, 'device' => $device] = scoringCustomer();
    $other = scoringCustomer();
    $other2 = scoringCustomer();

    // Another customer already paid the same destination within the hour.
    foreach ([$other, $other2] as $c) {
        app(EventIngestor::class)->ingest(scoringTransfer($c['account'], $c['device'], 50));
    }

    $f = featuresFor(scoringTransfer($account, $device, 50));

    expect($f['beneficiary_fan_out_1h'])->toBe(3)
        ->and($f['minutes_since_beneficiary_added'])->toBe(0);

    // A beneficiary registered 10 days ago on an older destination account.
    $old = Beneficiary::factory()->create(['account_id' => $account->id, 'beneficiary_account' => '7000000001', 'added_at' => now()->subDays(10), 'created_at' => now()->subDays(10)]);
    $f2 = featuresFor(scoringTransfer($account, $device, 50, '7000000001'));

    expect($f2['beneficiary_account_age_days'])->toBe(10)
        ->and($f2['beneficiary_is_new'])->toBe(0)
        ->and($old->exists)->toBeTrue();
});

it('counts distinct accounts used from one device', function () {
    ['device' => $device] = scoringCustomer();
    foreach (range(1, 4) as $i) {
        $acct = Account::factory()->create();
        Transaction::factory()->create(['account_id' => $acct->id, 'device_id' => $device->id]);
    }
    ['account' => $account] = scoringCustomer();

    $f = featuresFor(scoringTransfer($account, $device, 50));

    expect($f['device_account_count'])->toBe(5);
});

it('detects blacklist and whitelist hits', function () {
    ['account' => $account, 'device' => $device] = scoringCustomer();

    expect(featuresFor(scoringTransfer($account, $device, 10))['blacklist_hit'])->toBe(0);

    Watchlist::factory()->create(['list_type' => WatchlistType::Blacklist, 'entity_type' => WatchlistEntity::Beneficiary, 'entity_value' => '8000000008']);
    expect(featuresFor(scoringTransfer($account, $device, 10, '8000000008'))['blacklist_hit'])->toBe(1);

    Watchlist::factory()->create(['list_type' => WatchlistType::Whitelist, 'entity_type' => WatchlistEntity::Device, 'entity_value' => $device->device_fingerprint]);
    $f = featuresFor(scoringTransfer($account, $device, 10));
    expect($f['whitelist_hit'])->toBe(1)->and($f['blacklist_hit'])->toBe(0);

    $dev = Device::factory()->create();
    Watchlist::factory()->create(['list_type' => WatchlistType::Blacklist, 'entity_type' => WatchlistEntity::Device, 'entity_value' => $dev->device_fingerprint]);
    expect(featuresFor(scoringTransfer($account, $dev, 10))['blacklist_hit'])->toBe(1);
});

it('detects a recently raised limit', function () {
    ['account' => $account, 'device' => $device] = scoringCustomer();

    featuresFor(['event_type' => 'raise_limit', 'account_number' => $account->account_number, 'new_limit' => 90000, 'device_fingerprint' => $device->device_fingerprint]);

    expect($account->fresh()->transfer_limit)->toBe('90000.00')
        ->and(featuresFor(scoringTransfer($account, $device, 100))['limit_raised_recently'])->toBe(1);
});
