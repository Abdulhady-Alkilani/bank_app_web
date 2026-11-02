<?php

namespace App\Services\Investigation;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the visibility-scoped, filterable and priority/score-sorted alert query shared by the UI and the staff API.
 */
class AlertQueue
{
    /**
     * @param  array<string, mixed>  $filters  status, priority, min_score, max_score, channel, date_from, date_to, min_amount, max_amount, mine
     * @return Builder<Alert>
     */
    public function query(User $user, array $filters = []): Builder
    {
        $query = Alert::query()
            ->select('alerts.*')
            ->join('risk_scores', 'risk_scores.id', '=', 'alerts.risk_score_id')
            ->leftJoin('transactions', 'transactions.id', '=', 'risk_scores.transaction_id')
            ->with(['riskScore.transaction.account', 'riskScore.event.account', 'assignee', 'case']);

        $this->scopeVisible($query, $user);

        $query
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('alerts.status', $v))
            ->when($filters['priority'] ?? null, fn (Builder $q, $v) => $q->where('alerts.priority', $v))
            ->when($this->number($filters['min_score'] ?? null) !== null, fn (Builder $q) => $q->where('risk_scores.score', '>=', $this->number($filters['min_score'])))
            ->when($this->number($filters['max_score'] ?? null) !== null, fn (Builder $q) => $q->where('risk_scores.score', '<=', $this->number($filters['max_score'])))
            ->when($filters['channel'] ?? null, fn (Builder $q, $v) => $q->where('transactions.channel', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('alerts.created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('alerts.created_at', '<=', $v))
            ->when($this->number($filters['min_amount'] ?? null) !== null, fn (Builder $q) => $q->where('transactions.amount', '>=', $this->number($filters['min_amount'])))
            ->when($this->number($filters['max_amount'] ?? null) !== null, fn (Builder $q) => $q->where('transactions.amount', '<=', $this->number($filters['max_amount'])))
            ->when(filter_var($filters['mine'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $q) => $q->where('alerts.assigned_to', $user->getKey()));

        return $query
            ->orderByRaw("CASE alerts.priority WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
            ->orderByDesc('risk_scores.score')
            ->orderByDesc('alerts.id');
    }

    /**
     * @param  Builder<Alert>  $query
     */
    public function scopeVisible(Builder $query, User $user): void
    {
        if ($user->hasRole(['supervisor', 'admin'])) {
            return;
        }

        $query->where(fn (Builder $q) => $q->whereNull('alerts.assigned_to')->orWhere('alerts.assigned_to', $user->getKey()));
    }

    private function number(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }
}
