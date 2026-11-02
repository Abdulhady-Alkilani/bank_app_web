<?php

namespace App\Services\Investigation;

use App\Enums\TransactionStatus;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Computes a customer's usual behaviour so an analyst can compare it with the flagged event.
 */
class CustomerInsights
{
    /**
     * @return array{count: int, avg_amount: float, max_amount: float, hours: array<int, int>, cities: array<int, string>, device_ids: array<int, int>}
     */
    public function patterns(Customer $customer): array
    {
        $transactions = $this->recentTransactions($customer);
        $completed = $transactions->where('status', TransactionStatus::Completed);
        $basis = $completed->isNotEmpty() ? $completed : $transactions;

        $pattern = $customer->usage_pattern ?? [];

        $hours = $basis->map(fn (Transaction $t) => (int) $t->transaction_time->format('G'))->countBy()->sortDesc()->keys()->take(4)->values()->all();
        $cities = $basis->pluck('city')->filter()->countBy()->sortDesc()->keys()->take(4)->values()->all();

        return [
            'count' => $basis->count(),
            'avg_amount' => $basis->isNotEmpty() ? round((float) $basis->avg('amount'), 2) : (float) ($pattern['avg_txn_amount'] ?? 0),
            'max_amount' => $basis->isNotEmpty() ? (float) $basis->max('amount') : 0.0,
            'hours' => $hours !== [] ? $hours : (isset($pattern['typical_hours']) ? array_values((array) $pattern['typical_hours']) : []),
            'cities' => $cities !== [] ? $cities : array_values((array) ($pattern['cities'] ?? [])),
            'device_ids' => $customer->customerDevices()->pluck('device_id')->all(),
        ];
    }

    /**
     * Compare the flagged item of an alert with the customer's habits.
     *
     * @return array<int, array{label: string, current: string, usual: string, unusual: bool}>
     */
    public function compare(Customer $customer, Alert $alert): array
    {
        $score = $alert->riskScore;
        $transaction = $score?->transaction;
        if (! $transaction) {
            return [];
        }

        $patterns = $this->patterns($customer);
        $rows = [];

        $ratio = $patterns['avg_amount'] > 0 ? (float) $transaction->amount / $patterns['avg_amount'] : null;
        $rows[] = [
            'label' => __('analyst.customer.amount'),
            'current' => number_format((float) $transaction->amount, 2),
            'usual' => number_format($patterns['avg_amount'], 2).($ratio ? ' (×'.round($ratio, 1).')' : ''),
            'unusual' => $ratio !== null && $ratio >= 3,
        ];

        $hour = (int) $transaction->transaction_time->format('G');
        $rows[] = [
            'label' => __('analyst.customer.hour'),
            'current' => sprintf('%02d:00', $hour),
            'usual' => $patterns['hours'] === [] ? '—' : collect($patterns['hours'])->map(fn ($h) => sprintf('%02d:00', $h))->implode(', '),
            'unusual' => $patterns['hours'] !== [] && ! in_array($hour, $patterns['hours'], true),
        ];

        $rows[] = [
            'label' => __('analyst.customer.city'),
            'current' => $transaction->city ?? '—',
            'usual' => $patterns['cities'] === [] ? '—' : implode(', ', $patterns['cities']),
            'unusual' => $transaction->city !== null && $patterns['cities'] !== [] && ! in_array($transaction->city, $patterns['cities'], true),
        ];

        $knownDevice = $transaction->device_id === null || in_array($transaction->device_id, $patterns['device_ids'], true);
        $rows[] = [
            'label' => __('analyst.customer.device'),
            'current' => $transaction->device?->device_fingerprint ? substr($transaction->device->device_fingerprint, 0, 12) : '—',
            'usual' => __('analyst.customer.known_devices', ['count' => count($patterns['device_ids'])]),
            'unusual' => ! $knownDevice,
        ];

        return $rows;
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function recentTransactions(Customer $customer): Collection
    {
        return Transaction::query()
            ->whereIn('account_id', $customer->accounts()->pluck('id'))
            ->where('transaction_time', '>=', now()->subDays(120))
            ->latest('transaction_time')
            ->limit(300)
            ->get();
    }
}
