<?php

namespace App\Services\Approvals;

use App\Enums\AccountActionStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\CaseStatus;
use App\Exceptions\ApprovalException;
use App\Models\AccountAction;
use App\Models\Approval;
use App\Models\InvestigationCase;
use App\Models\User;
use App\Services\Accounts\AccountActionService;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Four-eyes approval workflow: the requester can never approve their own request.
 */
class ApprovalService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AccountActionService $actions,
    ) {}

    public function request(ApprovalType $type, Model $subject, User $requester, string $reason): Approval
    {
        $approval = Approval::create([
            'type' => $type,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'requested_by' => $requester->getKey(),
            'status' => ApprovalStatus::Pending,
            'reason' => $reason,
        ]);

        $this->audit->log('approval.requested', $approval, [], ['type' => $type->value, 'reason' => $reason], $requester);

        return $approval;
    }

    public function approve(Approval $approval, User $approver): Approval
    {
        $this->guardDecision($approval, $approver);

        return DB::transaction(function () use ($approval, $approver) {
            $approval->update([
                'status' => ApprovalStatus::Approved,
                'approved_by' => $approver->getKey(),
                'decided_at' => now(),
            ]);

            $approval->actions()
                ->where('status', AccountActionStatus::PendingApproval)
                ->get()
                ->each(fn (AccountAction $action) => $this->actions->executePending($action));

            $subject = $approval->subject;
            if ($approval->type === ApprovalType::CloseHighValueCase && $subject instanceof InvestigationCase) {
                $subject->update(['status' => CaseStatus::Closed]);
            }

            $this->audit->log('approval.approved', $approval, ['status' => ApprovalStatus::Pending->value], ['status' => ApprovalStatus::Approved->value], $approver);

            return $approval->refresh();
        });
    }

    public function reject(Approval $approval, User $approver, string $reason): Approval
    {
        $this->guardDecision($approval, $approver);

        return DB::transaction(function () use ($approval, $approver, $reason) {
            $approval->update([
                'status' => ApprovalStatus::Rejected,
                'approved_by' => $approver->getKey(),
                'decided_at' => now(),
            ]);

            $approval->actions()
                ->where('status', AccountActionStatus::PendingApproval)
                ->update(['status' => AccountActionStatus::Rejected->value]);

            $subject = $approval->subject;
            if ($approval->type === ApprovalType::CloseHighValueCase && $subject instanceof InvestigationCase
                && $subject->status === CaseStatus::PendingApproval) {
                $subject->update(['status' => CaseStatus::Investigating]);
                $this->audit->log('case.reopened_after_rejection', $subject, ['status' => CaseStatus::PendingApproval->value], ['status' => CaseStatus::Investigating->value, 'approval_id' => $approval->id], $approver);
            }

            $this->audit->log('approval.rejected', $approval, ['status' => ApprovalStatus::Pending->value], ['status' => ApprovalStatus::Rejected->value, 'reason' => $reason], $approver);

            return $approval->refresh();
        });
    }

    private function guardDecision(Approval $approval, User $approver): void
    {
        if ($approval->status !== ApprovalStatus::Pending) {
            throw new ApprovalException(__('common.approval_not_pending'));
        }

        if ($approval->requested_by === $approver->getKey()) {
            throw new ApprovalException(__('common.four_eyes_violation'));
        }

        if (! $approver->hasPermission('approvals.approve')) {
            throw new ApprovalException(__('common.approval_forbidden'));
        }
    }
}
