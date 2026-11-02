<?php

namespace App\Services\Investigation;

use App\Enums\AlertStatus;
use App\Enums\AnalystDecision;
use App\Enums\LabelSource;
use App\Models\Alert;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Labels\LabelService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AlertDecisionService
{
    public function __construct(
        private readonly LabelService $labels,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Record the analyst decision on an alert: updates the alert, writes a training label (for fraud / false-positive
     * decisions) and an audit entry. Authorization is the caller's responsibility.
     */
    public function decide(Alert $alert, AnalystDecision $decision, string $reason, User $user): Alert
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('analyst.errors.reason_required'));
        }

        return DB::transaction(function () use ($alert, $decision, $reason, $user) {
            $old = ['status' => $alert->status->value, 'decision' => $alert->decision?->value];

            $alert->update([
                'decision' => $decision,
                'decision_reason' => $reason,
                'decided_by' => $user->getKey(),
                'decided_at' => now(),
                'status' => match ($decision) {
                    AnalystDecision::ConfirmedFraud, AnalystDecision::FalsePositive => AlertStatus::Closed,
                    AnalystDecision::Escalated => AlertStatus::Escalated,
                    AnalystDecision::InfoRequested => AlertStatus::PendingInfo,
                },
                'assigned_to' => $alert->assigned_to ?? $user->getKey(),
            ]);

            if (in_array($decision, [AnalystDecision::ConfirmedFraud, AnalystDecision::FalsePositive], true)) {
                $score = $alert->riskScore;
                $this->labels->record(
                    $score?->transaction,
                    $score?->event,
                    $alert,
                    $decision === AnalystDecision::ConfirmedFraud,
                    LabelSource::Analyst,
                    $user,
                );
            }

            $this->audit->log('alert.decided', $alert, $old, [
                'status' => $alert->status->value,
                'decision' => $decision->value,
                'reason' => $reason,
            ], $user);

            return $alert->refresh();
        });
    }
}
