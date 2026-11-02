<?php

namespace App\Services\Alerts;

use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Enums\Decision;
use App\Enums\Priority;
use App\Models\Account;
use App\Models\Alert;
use App\Models\InvestigationCase;
use App\Models\RiskScore;
use App\Models\User;
use App\Notifications\HighRiskAlertNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use App\Support\Masker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AlertService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function priorityFor(int $score): Priority
    {
        return match (true) {
            $score >= 90 => Priority::Critical,
            $score >= 75 => Priority::High,
            $score >= 55 => Priority::Medium,
            default => Priority::Low,
        };
    }

    /**
     * Create (once) the alert for a challenge/block risk score: priority, auto-assignment, case linking
     * and notifications. Allow decisions produce no alert.
     */
    public function createForRiskScore(RiskScore $riskScore): ?Alert
    {
        if ($riskScore->decision === Decision::Allow) {
            return null;
        }

        if ($existing = Alert::where('risk_score_id', $riskScore->id)->first()) {
            return $existing;
        }

        $riskScore->loadMissing('transaction.account', 'event.account');
        $account = $riskScore->transaction?->account ?? $riskScore->event?->account;
        $priority = $this->priorityFor($riskScore->score);

        $alert = DB::transaction(function () use ($riskScore, $account, $priority) {
            $assignee = $this->pickAnalyst();

            $alert = Alert::create([
                'risk_score_id' => $riskScore->id,
                'status' => AlertStatus::Open,
                'priority' => $priority,
                'assigned_to' => $assignee?->id,
            ]);

            if ($account) {
                $this->attachToCase($alert, $account, $priority, $assignee);
            }

            $this->audit->log('alert.created', $alert, [], [
                'risk_score_id' => $riskScore->id,
                'priority' => $priority->value,
                'assigned_to' => $assignee?->id,
                'case_id' => $alert->case_id,
            ]);

            return $alert;
        });

        $this->notify($alert, $riskScore, $account);

        return $alert;
    }

    /**
     * Active analyst with the fewest open alerts who is still under today's capacity.
     */
    public function pickAnalyst(): ?User
    {
        $analysts = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'analyst'))
            ->orderBy('id')
            ->get();

        if ($analysts->isEmpty()) {
            return null;
        }

        $open = Alert::query()
            ->whereNotNull('assigned_to')
            ->where('status', '!=', AlertStatus::Closed->value)
            ->groupBy('assigned_to')
            ->selectRaw('assigned_to, count(*) as c')
            ->pluck('c', 'assigned_to');

        $today = Alert::query()
            ->whereNotNull('assigned_to')
            ->where('created_at', '>=', now()->startOfDay())
            ->groupBy('assigned_to')
            ->selectRaw('assigned_to, count(*) as c')
            ->pluck('c', 'assigned_to');

        $capacity = (int) $this->settings->get('alerts.analyst_daily_capacity', 50);

        return $analysts
            ->filter(fn (User $u) => (int) ($today[$u->id] ?? 0) < $capacity)
            ->sortBy(fn (User $u) => [(int) ($open[$u->id] ?? 0), $u->id])
            ->first();
    }

    private function attachToCase(Alert $alert, Account $account, Priority $priority, ?User $assignee): void
    {
        $case = InvestigationCase::query()
            ->where('status', '!=', CaseStatus::Closed->value)
            ->whereHas('alerts.riskScore', function ($q) use ($account) {
                $q->whereHas('transaction', fn ($t) => $t->where('account_id', $account->id))
                    ->orWhereHas('event', fn ($e) => $e->where('account_id', $account->id));
            })
            ->latest('id')
            ->first();

        if ($case) {
            if ($this->rank($priority) > $this->rank($case->priority)) {
                $case->update(['priority' => $priority]);
            }
        } elseif (in_array($priority, [Priority::High, Priority::Critical], true)) {
            $case = InvestigationCase::create([
                'title' => __('scoring.case_title', ['account' => Masker::maskAccount($account->account_number)], 'ar'),
                'assigned_to' => $assignee?->id,
                'status' => CaseStatus::Open,
                'priority' => $priority,
            ]);
        }

        if ($case) {
            $alert->update(['case_id' => $case->id]);
        }
    }

    private function rank(Priority $p): int
    {
        return match ($p) {
            Priority::Low => 1,
            Priority::Medium => 2,
            Priority::High => 3,
            Priority::Critical => 4,
        };
    }

    private function notify(Alert $alert, RiskScore $riskScore, ?Account $account): void
    {
        $notification = new HighRiskAlertNotification($alert, $account?->account_number ?? '', $riskScore->score);

        $recipients = collect();
        if ($alert->assignee) {
            $recipients->push($alert->assignee);
        }

        if ($alert->priority === Priority::Critical) {
            $recipients = $recipients->merge(
                User::query()->where('is_active', true)->whereHas('roles', fn ($q) => $q->where('slug', 'analyst'))->get(),
            );
        }

        $recipients = $recipients->unique('id');

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
    }
}
