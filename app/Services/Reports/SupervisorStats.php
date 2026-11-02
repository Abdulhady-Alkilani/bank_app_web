<?php

namespace App\Services\Reports;

use App\Enums\AlertStatus;
use App\Enums\AnalystDecision;
use App\Enums\ApprovalStatus;
use App\Enums\TransactionStatus;
use App\Models\AccountAction;
use App\Models\Alert;
use App\Models\Approval;
use App\Models\InvestigationCase;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * KPI aggregates shared by the supervisor dashboard (SUP-03) and compliance reports (SUP-06).
 */
class SupervisorStats
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $alerts = Alert::query()->with('assignee')->whereBetween('created_at', [$from, $to])->get();
        $decided = Alert::query()->whereNotNull('decided_at')->whereBetween('decided_at', [$from, $to])->get();

        $handling = $decided->map(fn (Alert $a) => $a->created_at ? $a->created_at->diffInMinutes($a->decided_at, true) : null)
            ->filter(fn ($m) => $m !== null);

        $decisionCounts = $decided->groupBy(fn (Alert $a) => $a->decision?->value ?? 'none')->map->count();
        $falsePositives = (int) ($decisionCounts[AnalystDecision::FalsePositive->value] ?? 0);

        $blocked = Transaction::query()
            ->whereIn('status', [TransactionStatus::Blocked->value, TransactionStatus::Held->value])
            ->whereBetween('transaction_time', [$from, $to]);

        $perDay = $alerts->groupBy(fn (Alert $a) => $a->created_at->format('Y-m-d'))->map->count();
        $days = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $days[$day->format('Y-m-d')] = (int) ($perDay[$day->format('Y-m-d')] ?? 0);
        }

        $cases = InvestigationCase::query()->whereBetween('created_at', [$from, $to])->get();
        $approvals = Approval::query()->whereBetween('created_at', [$from, $to])->get();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'alerts_total' => $alerts->count(),
            'alerts_by_status' => $this->tally($alerts, fn (Alert $a) => $a->status->value, AlertStatus::values()),
            'alerts_by_priority' => $this->tally($alerts, fn (Alert $a) => $a->priority->value, ['low', 'medium', 'high', 'critical']),
            'alerts_per_day' => $days,
            'decided_total' => $decided->count(),
            'decisions' => $decisionCounts->all(),
            'avg_handling_minutes' => $handling->isEmpty() ? null : round($handling->avg(), 1),
            'false_positive_rate' => $decided->isEmpty() ? null : round($falsePositives / $decided->count() * 100, 1),
            'blocked_amount' => round((float) (clone $blocked)->sum('amount'), 2),
            'blocked_count' => (clone $blocked)->count(),
            'workload' => $this->workload(),
            'cases_total' => $cases->count(),
            'cases_by_status' => $this->tally($cases, fn ($c) => $c->status->value, ['open', 'investigating', 'pending_approval', 'closed']),
            'actions_by_type' => AccountAction::query()->whereBetween('created_at', [$from, $to])->get()
                ->groupBy(fn (AccountAction $a) => $a->action->value)->map->count()->all(),
            'approvals' => [
                'pending' => $approvals->where('status', ApprovalStatus::Pending)->count(),
                'approved' => $approvals->where('status', ApprovalStatus::Approved)->count(),
                'rejected' => $approvals->where('status', ApprovalStatus::Rejected)->count(),
            ],
        ];
    }

    /**
     * Open alerts per analyst right now.
     *
     * @return array<string, int> analyst name => open alerts
     */
    public function workload(): array
    {
        return Alert::query()->with('assignee')->whereNotNull('assigned_to')
            ->where('status', '!=', AlertStatus::Closed->value)->get()
            ->groupBy(fn (Alert $a) => $a->assignee?->name ?? '#'.$a->assigned_to)->map->count()->sortDesc()->all();
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @param  array<int, string>  $keys
     * @return array<string, int>
     */
    private function tally(Collection $items, callable $key, array $keys): array
    {
        $counts = $items->groupBy($key)->map->count();

        return collect($keys)->mapWithKeys(fn (string $k) => [$k => (int) ($counts[$k] ?? 0)])->all();
    }
}
