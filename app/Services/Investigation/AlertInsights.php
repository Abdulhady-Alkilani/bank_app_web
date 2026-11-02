<?php

namespace App\Services\Investigation;

use App\Models\Account;
use App\Models\AccountEvent;
use App\Models\Alert;
use App\Models\RiskScore;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;

/**
 * Read-model helpers that explain an alert: account, reasons, features and surrounding timeline.
 */
class AlertInsights
{
    public function account(Alert $alert): ?Account
    {
        $score = $alert->riskScore;

        return $score?->transaction?->account ?? $score?->event?->account;
    }

    public function subjectTime(Alert $alert): CarbonInterface
    {
        $score = $alert->riskScore;

        return $score?->transaction?->transaction_time
            ?? $score?->event?->event_time
            ?? $alert->created_at
            ?? now();
    }

    /**
     * Reasons are tolerant to missing keys: [{code,text,weight|impact,source}] or plain strings.
     *
     * @return array<int, array{code: ?string, text: string, impact: int|float|null, source: ?string}>
     */
    public function reasons(?RiskScore $score): array
    {
        return collect($score?->reasons ?? [])
            ->map(function ($reason) {
                if (is_string($reason)) {
                    $reason = ['text' => $reason];
                }
                if (! is_array($reason)) {
                    return null;
                }
                $code = $reason['code'] ?? null;
                $text = $reason['text'] ?? $reason['message'] ?? $reason['description'] ?? null;
                if ($text === null && $code !== null) {
                    $text = Lang::has('analyst.reason_codes.'.$code) ? __('analyst.reason_codes.'.$code) : $code;
                }

                return [
                    'code' => $code,
                    'text' => (string) ($text ?? '—'),
                    'impact' => $reason['weight'] ?? $reason['impact'] ?? null,
                    'source' => $reason['source'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, value: string}>
     */
    public function features(?RiskScore $score): array
    {
        return collect($score?->features ?? [])
            ->map(fn ($value, $key) => [
                'key' => (string) $key,
                'label' => Lang::has('analyst.features.'.$key) ? __('analyst.features.'.$key) : (string) $key,
                'value' => match (true) {
                    is_bool($value) => $value ? __('common.yes') : __('common.no'),
                    is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
                    $value === null => '—',
                    default => (string) $value,
                },
            ])
            ->values()
            ->all();
    }

    /**
     * Transactions and account events of the alert's account within 24h either side of the flagged item, oldest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function timeline(Alert $alert): Collection
    {
        $account = $this->account($alert);
        if (! $account) {
            return collect();
        }

        $at = $this->subjectTime($alert);
        $from = $at->copy()->subDay();
        $to = $at->copy()->addDay();
        $score = $alert->riskScore;

        $transactions = Transaction::query()
            ->with('riskScore')
            ->where('account_id', $account->id)
            ->whereBetween('transaction_time', [$from, $to])
            ->get()
            ->map(fn (Transaction $t) => [
                'time' => $t->transaction_time,
                'kind' => 'transaction',
                'title' => __('analyst.timeline.transaction'),
                'detail' => trim(number_format((float) $t->amount, 2).' · '.$t->channel.' · '.($t->city ?? '')),
                'status' => $t->status->label(),
                'score' => $t->riskScore?->score,
                'current' => $score?->transaction_id === $t->id,
            ]);

        $events = AccountEvent::query()
            ->with('riskScore')
            ->where('account_id', $account->id)
            ->whereBetween('event_time', [$from, $to])
            ->get()
            ->map(fn (AccountEvent $e) => [
                'time' => $e->event_time,
                'kind' => 'event',
                'title' => $e->event_type->label(),
                'detail' => trim(($e->ip_address ?? '').' '.collect($e->details ?? [])->filter(fn ($v) => is_scalar($v))->map(fn ($v, $k) => "$k: $v")->implode(', ')),
                'status' => null,
                'score' => $e->riskScore?->score,
                'current' => $score?->event_id === $e->id,
            ]);

        return $transactions->concat($events)->sortBy(fn (array $row) => $row['time']->getTimestamp())->values();
    }
}
