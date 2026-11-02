<?php

namespace App\Services\Accounts;

use App\Enums\AccountActionStatus;
use App\Enums\AccountActionType;
use App\Enums\AccountStatus;
use App\Enums\ApprovalType;
use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\AccountAction;
use App\Models\Beneficiary;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use InvalidArgumentException;

class AccountActionService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Execute a system action immediately (no approval).
     *
     * @param  array{transaction?: Transaction|int|null, alert_id?: int|null, beneficiary?: Beneficiary|int|null, reason?: string|null, requested_by?: User|int|null}  $context
     */
    public function execute(AccountActionType $type, Account $account, array $context = []): AccountAction
    {
        $action = $this->makeAction($type, $account, $context, AccountActionStatus::Executed);
        $this->apply($action);

        return $action->refresh();
    }

    /**
     * Create a pending approval plus a pending action; it is executed when the approval is granted.
     *
     * @param  array{transaction?: Transaction|int|null, alert_id?: int|null, beneficiary?: Beneficiary|int|null}  $context
     */
    public function requestWithApproval(AccountActionType $type, Account $account, User $requester, string $reason, array $context = []): AccountAction
    {
        $approvalType = match ($type) {
            AccountActionType::FreezeAccount => ApprovalType::FreezeAccount,
            AccountActionType::UnfreezeAccount => ApprovalType::UnfreezeAccount,
            AccountActionType::FreezeBeneficiary => ApprovalType::FreezeBeneficiary,
            default => throw new InvalidArgumentException("Action [{$type->value}] does not support approval."),
        };

        $approval = app(ApprovalService::class)->request($approvalType, $account, $requester, $reason);

        $action = $this->makeAction($type, $account, $context + ['reason' => $reason, 'requested_by' => $requester], AccountActionStatus::PendingApproval, $approval->id);

        $this->audit->log('account_action.requested', $action, [], ['action' => $type->value, 'approval_id' => $approval->id], $requester);

        return $action;
    }

    /**
     * Apply a previously pending action (called by ApprovalService once approved).
     */
    public function executePending(AccountAction $action): AccountAction
    {
        $this->apply($action);

        return $action->refresh();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function makeAction(AccountActionType $type, Account $account, array $context, AccountActionStatus $status, ?int $approvalId = null): AccountAction
    {
        $requester = $context['requested_by'] ?? auth()->user();

        return AccountAction::create([
            'account_id' => $account->id,
            'transaction_id' => $this->id($context['transaction'] ?? null),
            'alert_id' => $context['alert_id'] ?? null,
            'approval_id' => $approvalId,
            'beneficiary_id' => $this->id($context['beneficiary'] ?? null),
            'action' => $type,
            'status' => $status,
            'requested_by' => $this->id($requester),
            'reason' => $context['reason'] ?? null,
        ]);
    }

    private function apply(AccountAction $action): void
    {
        $account = $action->account;
        $old = [];
        $new = [];
        $entity = $account;

        switch ($action->action) {
            case AccountActionType::FreezeAccount:
                $old = ['status' => $account->status->value];
                $account->update(['status' => AccountStatus::Frozen]);
                $new = ['status' => AccountStatus::Frozen->value];
                break;
            case AccountActionType::UnfreezeAccount:
                $old = ['status' => $account->status->value];
                $account->update(['status' => AccountStatus::Active]);
                $new = ['status' => AccountStatus::Active->value];
                break;
            case AccountActionType::FreezeBeneficiary:
                $entity = $action->beneficiary ?? throw new InvalidArgumentException('Beneficiary is required.');
                $old = ['is_frozen' => (bool) $entity->is_frozen];
                $entity->update(['is_frozen' => true]);
                $new = ['is_frozen' => true];
                break;
            case AccountActionType::BlockTransaction:
                $entity = $action->transaction ?? throw new InvalidArgumentException('Transaction is required.');
                $old = ['status' => $entity->status->value];
                $entity->update(['status' => TransactionStatus::Blocked]);
                $new = ['status' => TransactionStatus::Blocked->value];
                break;
            case AccountActionType::RequireOtp:
                $new = ['otp_required' => true];
                break;
        }

        $action->update(['status' => AccountActionStatus::Executed, 'executed_at' => now()]);

        $this->audit->log('account_action.'.$action->action->value, $entity, $old, $new + ['account_action_id' => $action->id]);
    }

    private function id(mixed $value): ?int
    {
        return match (true) {
            $value === null => null,
            is_object($value) => $value->getKey(),
            default => (int) $value,
        };
    }
}
