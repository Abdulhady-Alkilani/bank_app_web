<?php

namespace App\Services\Simulation;

use App\Enums\AccountStatus;
use App\Enums\LabelSource;
use App\Models\Account;
use App\Models\AccountEvent;
use App\Models\Beneficiary;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Device;
use App\Models\Transaction;
use App\Services\Labels\LabelService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Bulk traffic generator: normal customer behaviour plus mixed fraud patterns
 * (account takeover, mule fan-out, shared device) spread over the last N days.
 * Scenarios: normal (everything), mule, shared-device. All events go through the scorer.
 */
class BulkScenario
{
    public const SCENARIOS = ['normal', 'mule', 'shared-device'];

    private const CITIES = ['Sanaa', 'Aden', 'Taiz', 'Ibb', 'Hodeidah'];

    private string $scenario = 'normal';

    private string $prefix = 'sim-normal-';

    /** @var Collection<int, array{account: Account, customer: Customer, device: Device, ip: string, city: string, beneficiaries: Collection<int, Beneficiary>}> */
    private Collection $population;

    /** @var array<int, array{account: Account, customer: Customer, device: Device, ip: string, city: string, beneficiaries: Collection<int, Beneficiary>}> */
    private array $victimPool = [];

    private int $seq = 0;

    public function __construct(private readonly SimulationScorer $scorer, private readonly LabelService $labels) {}

    /**
     * @param  callable(int, int): void|null  $tick  called after every scored event with (done, total)
     * @return array{events: int, fraud_events: int, decisions: array<string, int>, fraud_caught: int, false_positives: int, frozen: int}
     */
    public function run(string $scenario, int $count, float $fraudRate, int $days, ?callable $tick = null): array
    {
        $this->scenario = $scenario;
        $this->prefix = "sim-{$scenario}-";
        $this->seq = 0;

        $this->buildPopulation(max(30, min(80, (int) ceil($count / 12))));

        $fraudEvents = $this->fraudPlan($scenario, max(0, (int) round($count * $fraudRate)), $days);
        $normalEvents = $scenario === 'normal' ? $this->normalPlan(max(0, $count - count($fraudEvents)), $days) : [];

        $plan = collect($normalEvents)->merge($fraudEvents)->sortBy(fn ($e) => $e['at']->getTimestamp())->values();
        $total = $plan->count();

        $stats = ['events' => 0, 'fraud_events' => 0, 'decisions' => ['allow' => 0, 'challenge' => 0, 'block' => 0], 'fraud_caught' => 0, 'false_positives' => 0, 'frozen' => 0];
        $frozen = [];

        foreach ($plan as $i => $event) {
            $number = $event['payload']['account_number'];

            if (! $event['fraud'] && isset($frozen[$number])) {
                $tick && $tick($i + 1, $total);

                continue;
            }

            $payload = $event['payload'] + [
                'event_time' => $event['at']->toIso8601String(),
                'idempotency_key' => $this->prefix.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
            ];

            $result = $this->scorer->score($payload);
            $stats['events']++;
            $stats['decisions'][$result['decision']]++;
            if ($result['decision'] === 'block') {
                $frozen[$number] = true;
            }

            if ($event['fraud']) {
                $stats['fraud_events']++;
                $stats['fraud_caught'] += $result['decision'] !== 'allow' ? 1 : 0;
            } else {
                $stats['false_positives'] += $result['decision'] !== 'allow' ? 1 : 0;
            }

            $this->label($result, $event['fraud']);
            $tick && $tick($i + 1, $total);
        }

        $stats['frozen'] = count($frozen);

        return $stats;
    }

    // ------------------------------------------------------------------ population

    private function buildPopulation(int $size): void
    {
        $this->population = collect(range(1, $size))->map(function (int $n) {
            $city = self::CITIES[$n % count(self::CITIES)];
            $customer = Customer::query()->updateOrCreate(['email' => "cust{$n}.{$this->scenario}@sim.bank.test"], [
                'name' => 'Sim Customer '.$n, 'phone' => '+96773'.str_pad((string) (1000000 + $n), 7, '0', STR_PAD_LEFT),
                'avg_balance' => random_int(1500, 4000),
                'usage_pattern' => ['typical_hours' => [8, 20], 'avg_txn_amount' => random_int(80, 300), 'cities' => [$city]],
            ]);

            $account = Account::query()->updateOrCreate(['account_number' => '4'.str_pad((string) (crc32($this->scenario) % 1000), 3, '0', STR_PAD_LEFT).str_pad((string) $n, 6, '0', STR_PAD_LEFT)], [
                'customer_id' => $customer->id, 'type' => 'current', 'status' => AccountStatus::Active, 'balance' => 80000, 'transfer_limit' => null,
            ]);
            $account->forceFill(['created_at' => now()->subYear()])->saveQuietly();

            $ip = '10.30.'.random_int(0, 250).'.'.random_int(1, 250);
            $device = Device::query()->firstOrCreate(['device_fingerprint' => $this->prefix.'dev-'.$n], ['device_type' => 'android', 'last_ip' => $ip, 'last_location' => $city]);
            CustomerDevice::query()->firstOrCreate(['customer_id' => $customer->id, 'device_id' => $device->id], ['is_trusted' => true, 'last_used_at' => now()->subDay()]);

            $beneficiaries = collect(range(1, 3))->map(fn (int $b) => Beneficiary::query()->firstOrCreate(
                ['account_id' => $account->id, 'beneficiary_account' => '30'.str_pad((string) ($n * 10 + $b), 8, '0', STR_PAD_LEFT)],
                ['beneficiary_name' => "Contact {$n}-{$b}", 'bank_name' => 'Bank '.chr(64 + $b), 'added_at' => now()->subDays(60)],
            ));

            return compact('account', 'customer', 'device', 'ip', 'city', 'beneficiaries');
        });

        $this->victimPool = $this->population->slice((int) floor($this->population->count() * 0.65))->values()->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function normalCustomers(): Collection
    {
        return $this->population->slice(0, (int) floor($this->population->count() * 0.65))->values();
    }

    // ------------------------------------------------------------------ normal traffic

    /**
     * @return array<int, array{at: Carbon, fraud: bool, payload: array<string, mixed>}>
     */
    private function normalPlan(int $count, int $days): array
    {
        $customers = $this->normalCustomers();
        $events = [];

        for ($i = 0; $i < $count; $i++) {
            $c = $customers->random();
            $at = $this->randomTime($days, 8, 20);
            $roll = random_int(1, 100);
            $base = $this->base($c);

            $events[] = ['at' => $at, 'fraud' => false, 'payload' => match (true) {
                $roll <= 60 => $this->transfer($c, $base, $c['beneficiaries']->random(), (int) round($c['customer']->avg_balance * random_int(2, 18) / 100)),
                $roll <= 90 => $base + ['event_type' => 'login'],
                $roll <= 95 => $base + ['event_type' => 'add_beneficiary', 'beneficiary_account' => '31'.random_int(10000000, 99999999), 'beneficiary_name' => 'New Contact '.random_int(1, 999), 'bank_name' => 'Bank A'],
                $roll <= 98 => $base + ['event_type' => 'raise_limit', 'new_limit' => random_int(20, 40) * 1000],
                default => $base + ['event_type' => 'change_phone', 'new_phone' => '+96773'.random_int(1000000, 9999999)],
            }];
        }

        return $events;
    }

    // ------------------------------------------------------------------ fraud patterns

    /**
     * @return array<int, array{at: Carbon, fraud: bool, payload: array<string, mixed>}>
     */
    private function fraudPlan(string $scenario, int $fraudEvents, int $days): array
    {
        $kinds = match ($scenario) {
            'mule' => ['mule'],
            'shared-device' => ['shared'],
            default => ['takeover', 'mule', 'shared'],
        };

        $incidents = max(1, (int) round($fraudEvents / 5));
        $events = [];
        $used = 0;

        for ($k = 0; $k < $incidents; $k++) {
            $kind = $kinds[$k % count($kinds)];
            $at = $this->randomTime(max(1, $days - 1), 1, 23);

            // Each incident takes its own victims so that earlier freezes do not hide later incidents.
            $events = array_merge($events, match ($kind) {
                'takeover' => $this->takeover($k, $at, $used),
                'mule' => $this->muleFanOut($k, $at, $used),
                default => $this->sharedDevice($k, $at, $used),
            });
        }

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    private function attacker(int $k, string $kind): array
    {
        return [
            'device_fingerprint' => $this->prefix.$kind.'-atk-'.$k,
            'device_type' => 'android',
            'ip_address' => '185.220.101.'.(10 + $k % 200),
            'city' => 'Moscow',
            'channel' => 'mobile',
        ];
    }

    private function nextVictim(int &$used): array
    {
        return $this->victimPool[$used++ % count($this->victimPool)];
    }

    /**
     * @return array<int, array{at: Carbon, fraud: bool, payload: array<string, mixed>}>
     */
    private function takeover(int $k, Carbon $at, int &$used): array
    {
        $v = $this->nextVictim($used);
        $a = $this->attacker($k, 'takeover') + ['account_number' => $v['account']->account_number];
        $mule = '32'.random_int(10000000, 99999999);

        return [
            ['at' => $at->copy(), 'fraud' => true, 'payload' => $a + ['event_type' => 'change_phone', 'new_phone' => '+7999'.random_int(1000000, 9999999)]],
            ['at' => $at->copy()->addMinute(), 'fraud' => true, 'payload' => $a + ['event_type' => 'login']],
            ['at' => $at->copy()->addMinutes(4), 'fraud' => true, 'payload' => $a + ['event_type' => 'add_beneficiary', 'beneficiary_account' => $mule, 'beneficiary_name' => 'Quick Pay', 'bank_name' => 'Bank Z']],
            ['at' => $at->copy()->addMinutes(8), 'fraud' => true, 'payload' => $a + ['event_type' => 'transfer', 'amount' => (int) round($v['customer']->avg_balance * 12), 'beneficiary_account' => $mule, 'beneficiary_name' => 'Quick Pay', 'bank_name' => 'Bank Z']],
        ];
    }

    /**
     * @return array<int, array{at: Carbon, fraud: bool, payload: array<string, mixed>}>
     */
    private function muleFanOut(int $k, Carbon $at, int &$used): array
    {
        $mule = Account::query()->updateOrCreate(['account_number' => '33'.str_pad((string) (100000 + $k), 8, '0', STR_PAD_LEFT)], [
            'customer_id' => $this->population->first()['customer']->id, 'type' => 'current', 'status' => AccountStatus::Active, 'balance' => 100,
        ]);
        $mule->forceFill(['created_at' => $at->copy()->subDays(3)])->saveQuietly();

        $events = [];
        foreach (range(1, 6) as $i) {
            $v = $this->nextVictim($used);
            $a = $this->attacker($k, 'mule') + ['account_number' => $v['account']->account_number];
            $events[] = ['at' => $at->copy()->addMinutes($i * 6), 'fraud' => true, 'payload' => $a + [
                'event_type' => 'transfer', 'amount' => (int) round($v['customer']->avg_balance * random_int(3, 5)),
                'beneficiary_account' => $mule->account_number, 'beneficiary_name' => 'Cash Hub', 'bank_name' => 'Internal',
            ]];
        }

        return $events;
    }

    /**
     * @return array<int, array{at: Carbon, fraud: bool, payload: array<string, mixed>}>
     */
    private function sharedDevice(int $k, Carbon $at, int &$used): array
    {
        $events = [];
        foreach (range(1, min(10, count($this->victimPool))) as $i) {
            $v = $this->nextVictim($used);
            $events[] = ['at' => $at->copy()->addMinutes($i * 25), 'fraud' => true, 'payload' => $this->attacker($k, 'shared') + [
                'event_type' => 'login', 'account_number' => $v['account']->account_number,
            ]];
        }

        return $events;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param  array{account: Account, customer: Customer, device: Device, ip: string, city: string}  $c
     * @return array<string, mixed>
     */
    private function base(array $c): array
    {
        return [
            'account_number' => $c['account']->account_number,
            'device_fingerprint' => $c['device']->device_fingerprint,
            'device_type' => 'android',
            'ip_address' => $c['ip'],
            'city' => $c['city'],
            'channel' => 'mobile',
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function transfer(array $c, array $base, Beneficiary $beneficiary, int $amount): array
    {
        return $base + [
            'event_type' => 'transfer', 'amount' => max(20, $amount),
            'beneficiary_account' => $beneficiary->beneficiary_account,
            'beneficiary_name' => $beneficiary->beneficiary_name, 'bank_name' => $beneficiary->bank_name,
        ];
    }

    private function randomTime(int $days, int $fromHour, int $toHour): Carbon
    {
        $at = now()->subDays(random_int(0, max(0, $days - 1)))->setTime(random_int($fromHour, $toHour), random_int(0, 59), random_int(0, 59));

        return $at->isFuture() ? $at->subDay() : $at;
    }

    /**
     * Label all fraud events as fraud and a sample of normal ones as legitimate (source: bank).
     *
     * @param  array<string, mixed>  $result
     */
    private function label(array $result, bool $fraud): void
    {
        if (! $fraud && random_int(1, 100) > 3) {
            return;
        }

        $subject = $result['subject_type'] === 'transaction'
            ? Transaction::find($result['subject_id'])
            : AccountEvent::find($result['subject_id']);

        if ($subject) {
            $this->labels->record(
                $subject instanceof Transaction ? $subject : null,
                $subject instanceof AccountEvent ? $subject : null,
                null,
                $fraud,
                LabelSource::Bank,
            );
        }
    }
}
