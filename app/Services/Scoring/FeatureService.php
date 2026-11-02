<?php

namespace App\Services\Scoring;

use App\Data\IngestedEvent;
use App\Enums\EventType;
use App\Enums\WatchlistType;
use App\Models\Account;
use App\Models\AccountEvent;
use App\Models\Beneficiary;
use App\Models\CustomerDevice;
use App\Models\Transaction;
use App\Models\Watchlist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Computes the model/rules features for an ingested event. History is read with SQL (portable across
 * MySQL/sqlite); all windows are relative to the event time, not "now", so replayed/simulated data scores
 * consistently. Booleans are returned as 1/0; unknown values are null (rules never match on null).
 */
class FeatureService
{
    /**
     * @return array<string, int|float|null>
     */
    public function compute(IngestedEvent $e): array
    {
        $t = $e->eventTime;
        $tx = $e->transaction();
        $amount = $tx ? (float) $tx->amount : 0.0;
        $customer = $e->customer;

        $avg = (float) $customer->avg_balance;
        if ($avg <= 0) {
            $avg = (float) data_get($customer->usage_pattern, 'avg_txn_amount', 0);
        }

        $blacklist = $this->watchlistHit(WatchlistType::Blacklist, $e);
        $whitelist = $this->watchlistHit(WatchlistType::Whitelist, $e);

        $beneficiary = $e->beneficiary;
        $deviceId = $e->device?->id;

        return [
            'amount' => round($amount, 2),
            'amount_to_avg_ratio' => $tx && $avg > 0 ? round($amount / $avg, 2) : 0.0,
            'is_new_device' => (int) $e->isNewDevice,
            'device_account_count' => $deviceId ? $this->deviceAccountCount($deviceId, $t) : 0,
            'logins_new_device_1h' => $this->loginsFromNewDevices($e),
            'minutes_since_phone_change' => $this->minutesSincePhoneChange($e),
            'minutes_since_beneficiary_added' => $beneficiary?->added_at ? $this->minutesBetween($beneficiary->added_at, $t) : null,
            'beneficiary_is_new' => $beneficiary ? (int) ($e->beneficiaryCreated || ($beneficiary->added_at && $this->minutesBetween($beneficiary->added_at, $t) < 1440)) : 0,
            'beneficiary_account_age_days' => $beneficiary ? $this->beneficiaryAccountAgeDays($beneficiary, $t) : null,
            'beneficiary_fan_out_1h' => $tx && $beneficiary ? $this->fanOut($beneficiary->beneficiary_account, $t) : 0,
            'txn_count_1h' => $this->txnCount1h($e->account, $t),
            'location_changed' => (int) $this->locationChanged($e),
            'hour_of_day' => (int) $t->format('G'),
            'limit_raised_recently' => (int) $this->limitRaisedRecently($e->account, $t),
            'blacklist_hit' => (int) $blacklist,
            'whitelist_hit' => (int) $whitelist,
            'account_frozen' => (int) $e->account->isFrozen(),
        ];
    }

    private function minutesBetween(Carbon $from, Carbon $to): int
    {
        return max(0, (int) floor($from->diffInMinutes($to, false)));
    }

    private function deviceAccountCount(int $deviceId, Carbon $t): int
    {
        $since = $t->copy()->subDays(30);

        $tx = DB::table('transactions')->where('device_id', $deviceId)->where('transaction_time', '>=', $since)->select('account_id');
        $ev = DB::table('account_events')->where('device_id', $deviceId)->where('event_time', '>=', $since)->select('account_id');

        return DB::query()->fromSub($tx->union($ev), 'u')->count();
    }

    private function loginsFromNewDevices(IngestedEvent $e): int
    {
        $trusted = CustomerDevice::where('customer_id', $e->customer->id)->where('is_trusted', true)->pluck('device_id');

        return AccountEvent::query()
            ->where('account_id', $e->account->id)
            ->where('event_type', EventType::Login->value)
            ->whereBetween('event_time', [$e->eventTime->copy()->subHour(), $e->eventTime])
            ->whereNotNull('device_id')
            ->whereNotIn('device_id', $trusted)
            ->count();
    }

    private function minutesSincePhoneChange(IngestedEvent $e): ?int
    {
        $t = $e->eventTime;
        $accountIds = Account::where('customer_id', $e->customer->id)->pluck('id');

        $latest = AccountEvent::query()
            ->whereIn('account_id', $accountIds)
            ->where('event_type', EventType::ChangePhone->value)
            ->where('event_time', '<=', $t)
            ->max('event_time');

        $candidates = array_filter([
            $latest ? Carbon::parse($latest) : null,
            $e->device?->sim_changed_at && $e->device->sim_changed_at->lte($t) ? $e->device->sim_changed_at : null,
        ]);

        if ($candidates === []) {
            return null;
        }

        return $this->minutesBetween(collect($candidates)->max(), $t);
    }

    private function beneficiaryAccountAgeDays(Beneficiary $b, Carbon $t): int
    {
        $first = Account::where('account_number', $b->beneficiary_account)->min('created_at');
        $seen = Beneficiary::where('beneficiary_account', $b->beneficiary_account)->min('created_at');

        $earliest = collect([$first, $seen])->filter()->map(fn ($v) => Carbon::parse($v))->min();

        return $earliest ? max(0, (int) floor($earliest->diffInDays($t, false))) : 0;
    }

    private function fanOut(string $beneficiaryAccount, Carbon $t): int
    {
        return DB::table('transactions as t')
            ->join('beneficiaries as b', 'b.id', '=', 't.beneficiary_id')
            ->where('b.beneficiary_account', $beneficiaryAccount)
            ->whereBetween('t.transaction_time', [$t->copy()->subHour(), $t])
            ->distinct()
            ->count('t.account_id');
    }

    private function txnCount1h(Account $account, Carbon $t): int
    {
        return Transaction::query()
            ->where('account_id', $account->id)
            ->whereBetween('transaction_time', [$t->copy()->subHour(), $t])
            ->count();
    }

    private function locationChanged(IngestedEvent $e): bool
    {
        if (blank($e->city)) {
            return false;
        }

        $usual = array_map('mb_strtolower', (array) data_get($e->customer->usage_pattern, 'cities', []));
        if ($usual !== []) {
            return ! in_array(mb_strtolower($e->city), $usual, true);
        }

        return filled($e->previousLocation) && mb_strtolower($e->previousLocation) !== mb_strtolower($e->city);
    }

    private function limitRaisedRecently(Account $account, Carbon $t): bool
    {
        return AccountEvent::query()
            ->where('account_id', $account->id)
            ->where('event_type', EventType::RaiseLimit->value)
            ->whereBetween('event_time', [$t->copy()->subDay(), $t])
            ->exists();
    }

    private function watchlistHit(WatchlistType $list, IngestedEvent $e): bool
    {
        $pairs = array_filter([
            'device' => $e->device?->device_fingerprint,
            'ip' => $e->ip,
            'account' => $e->account->account_number,
            'beneficiary' => $e->beneficiary?->beneficiary_account,
        ]);

        // A whitelist only vouches for the sending side (device / account), never for the destination.
        if ($list === WatchlistType::Whitelist) {
            $pairs = array_intersect_key($pairs, ['device' => 1, 'account' => 1]);
        }

        if ($pairs === []) {
            return false;
        }

        return Watchlist::query()
            ->where('list_type', $list->value)
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as $entity => $value) {
                    $q->orWhere(fn ($w) => $w->where('entity_type', $entity)->where('entity_value', $value));
                }
            })
            ->exists();
    }
}
