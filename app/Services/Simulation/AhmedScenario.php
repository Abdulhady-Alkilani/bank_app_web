<?php

namespace App\Services\Simulation;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Device;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * The account-takeover timeline of the specification, played for Ahmed:
 *
 *  20:45  normal login from his own phone
 *  21:01  phone number changed from the attacker's device D77, 21:02 login from D77 (foreign IP) 40 s later
 *  (3 days) the same device D77 was seen on 12 other victim accounts
 *  21:05  transfer limit raised and a 6-day-old internal mule account added as beneficiary
 *  21:09  14,500 transfer to the mule (about 18x Ahmed's 800 average)
 *  21:15  eight more victims pay the same mule within the hour (fan-out to 9 accounts in total)
 */
class AhmedScenario
{
    public const PREFIX = 'sim-ahmed-';

    public const ATTACKER_DEVICE = 'sim-ahmed-D77';

    public const MULE_ACCOUNT = '2000000999';

    public const VICTIMS = 12;

    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    private int $seq = 0;

    private Carbon $base;

    public function __construct(private readonly SimulationScorer $scorer) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, account: Account, alerts: int, cases: int}
     */
    public function run(?callable $progress = null): array
    {
        $this->rows = [];
        $this->seq = 0;
        $this->base = today()->setTime(21, 0);
        if ($this->base->isFuture()) {
            $this->base->subDay();
        }

        $account = Account::query()->where('account_number', SimulationReset::AHMED_ACCOUNT)->with('customer')->first()
            ?? throw new RuntimeException('Ahmed\'s demo account (1000000001) was not found. Run the seeders first.');
        $customer = $account->customer;
        $trusted = $customer->customerDevices()->where('is_trusted', true)->with('device')->first()?->device
            ?? throw new RuntimeException('Ahmed has no trusted device. Run the seeders first.');

        // Baseline: Ahmed's average balance is 800, so 14,500 is about 18x his usual amount.
        $customer->forceFill(['avg_balance' => 800])->save();

        $mule = $this->createMule();
        $victims = $this->createVictims();

        $attacker = ['device_fingerprint' => self::ATTACKER_DEVICE, 'device_type' => 'android', 'ip_address' => '185.220.101.7', 'city' => 'Moscow', 'channel' => 'mobile'];
        $own = ['device_fingerprint' => $trusted->device_fingerprint, 'device_type' => 'android', 'ip_address' => '10.10.0.5', 'city' => 'Sanaa', 'channel' => 'mobile'];
        $acct = $account->account_number;

        $step = fn (string $time, string $label, array $payload) => $this->step($progress, $time, $label, $payload);

        $step('20:45:00', 'Login (own phone, Sanaa)', $own + ['event_type' => 'login', 'account_number' => $acct]);
        $step('21:01:20', 'Phone number changed (device D77)', $attacker + ['event_type' => 'change_phone', 'account_number' => $acct, 'new_phone' => '+79990001122']);
        $step('21:02:00', 'Login from new device D77 + foreign IP', $attacker + ['event_type' => 'login', 'account_number' => $acct]);

        $this->victimLogins($progress, $victims, $attacker);

        $step('21:05:00', 'Transfer limit raised to 50,000', $attacker + ['event_type' => 'raise_limit', 'account_number' => $acct, 'new_limit' => 50000]);
        $step('21:05:40', 'Beneficiary added (mule account, 6 days old)', $attacker + [
            'event_type' => 'add_beneficiary', 'account_number' => $acct,
            'beneficiary_account' => $mule->account_number, 'beneficiary_name' => 'Fast Cash Trading', 'bank_name' => 'Internal',
        ]);
        $step('21:09:00', 'Transfer 14,500 to the mule (~18x average)', $attacker + [
            'event_type' => 'transfer', 'account_number' => $acct, 'amount' => 14500,
            'beneficiary_account' => $mule->account_number, 'beneficiary_name' => 'Fast Cash Trading', 'bank_name' => 'Internal',
        ]);

        foreach ($victims->take(8)->values() as $i => $victim) {
            $time = $this->base->copy()->setTime(21, 15)->addMinutes($i * 5)->format('H:i:s');
            $step($time, 'Victim '.($i + 1).' pays the mule', $attacker + [
                'event_type' => 'transfer', 'account_number' => $victim->account_number, 'amount' => 6000 + $i * 500,
                'beneficiary_account' => $mule->account_number, 'beneficiary_name' => 'Fast Cash Trading', 'bank_name' => 'Internal',
            ]);
        }

        $account->refresh();
        $alerts = Alert::query()->whereHas('riskScore', fn ($q) => $q
            ->whereHas('transaction', fn ($t) => $t->where('idempotency_key', 'like', self::PREFIX.'%'))
            ->orWhereHas('event', fn ($e) => $e->where('idempotency_key', 'like', self::PREFIX.'%')))->get();

        return [
            'rows' => $this->rows,
            'account' => $account,
            'alerts' => $alerts->count(),
            'cases' => $alerts->pluck('case_id')->filter()->unique()->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function step(?callable $progress, string $time, string $label, array $payload): array
    {
        [$h, $m, $s] = array_map('intval', explode(':', $time));
        $at = $this->base->copy()->setTime($h, $m, $s);

        $payload += [
            'event_time' => $at->toIso8601String(),
            'idempotency_key' => self::PREFIX.str_pad((string) ++$this->seq, 3, '0', STR_PAD_LEFT),
        ];

        $result = $this->scorer->score($payload);
        $row = [
            'time' => $time,
            'event' => $label,
            'score' => $result['score'],
            'decision' => $result['decision'],
            'reasons' => $this->topReasons($result['reasons']),
            'type' => $payload['event_type'],
            'account' => $payload['account_number'],
        ];
        $this->rows[] = $row;
        $progress && $progress($row);

        return $row;
    }

    /**
     * D77 appears on 12 other victim accounts during the previous 3 days (one aggregated row).
     *
     * @param  Collection<int, Account>  $victims
     * @param  array<string, mixed>  $attacker
     */
    private function victimLogins(?callable $progress, $victims, array $attacker): void
    {
        $results = [];
        foreach ($victims as $i => $victim) {
            $payload = $attacker + [
                'event_type' => 'login',
                'account_number' => $victim->account_number,
                'event_time' => $this->base->copy()->subDays(3)->addHours($i * 5)->toIso8601String(),
                'idempotency_key' => self::PREFIX.'v'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
            ];
            $results[] = $this->scorer->score($payload);
        }

        $max = collect($results)->sortByDesc('score')->first();
        $row = [
            'time' => '-3d..now',
            'event' => 'D77 login on '.count($results).' other victim accounts',
            'score' => $max['score'],
            'decision' => $max['decision'],
            'reasons' => $this->topReasons($max['reasons']),
            'type' => 'login',
            'account' => '*',
        ];
        $this->rows[] = $row;
        $progress && $progress($row);
    }

    /**
     * @param  array<int, array<string, mixed>>  $reasons
     */
    private function topReasons(array $reasons): string
    {
        return collect($reasons)
            ->sortByDesc(fn ($r) => abs((float) ($r['weight'] ?? 0)))
            ->take(2)
            ->map(fn ($r) => str_contains($t = (string) ($r['text_en'] ?? $r['text'] ?? $r['code'] ?? ''), ' — ') ? substr($t, strpos($t, ' — ') + 5) : $t)
            ->implode('; ');
    }

    private function createMule(): Account
    {
        $customer = Customer::query()->updateOrCreate(['email' => 'mule.ahmed@sim.bank.test'], [
            'name' => 'Fast Cash Trading', 'phone' => '+967770000999', 'avg_balance' => 300,
            'usage_pattern' => ['cities' => ['Sanaa'], 'avg_txn_amount' => 100],
        ]);

        $account = Account::query()->firstOrNew(['account_number' => self::MULE_ACCOUNT]);
        $account->fill(['customer_id' => $customer->id, 'type' => 'current', 'status' => AccountStatus::Active, 'balance' => 150]);
        $account->save();
        // The mule account was opened 6 days before the attack.
        $account->forceFill(['created_at' => $this->base->copy()->subDays(6), 'updated_at' => $this->base->copy()->subDays(6)])->saveQuietly();

        return $account;
    }

    /**
     * @return Collection<int, Account>
     */
    private function createVictims()
    {
        return collect(range(1, self::VICTIMS))->map(function (int $i) {
            $customer = Customer::query()->updateOrCreate(['email' => "victim{$i}.ahmed@sim.bank.test"], [
                'name' => 'Victim '.$i, 'phone' => '+96777100'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'avg_balance' => 800, 'usage_pattern' => ['cities' => ['Sanaa'], 'avg_txn_amount' => 120],
            ]);

            $account = Account::query()->updateOrCreate(['account_number' => '20000010'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)], [
                'customer_id' => $customer->id, 'type' => 'current', 'status' => AccountStatus::Active, 'balance' => 30000,
            ]);
            $account->forceFill(['created_at' => $this->base->copy()->subYear()])->saveQuietly();

            $device = Device::query()->firstOrCreate(['device_fingerprint' => self::PREFIX.'victim-'.$i], ['device_type' => 'android', 'last_ip' => '10.20.0.'.$i, 'last_location' => 'Sanaa']);
            CustomerDevice::query()->firstOrCreate(['customer_id' => $customer->id, 'device_id' => $device->id], ['is_trusted' => true, 'last_used_at' => $this->base->copy()->subWeek()]);

            return $account;
        });
    }
}
