<?php

namespace App\Services\Investigation;

use App\Enums\AccountActionStatus;
use App\Enums\AccountActionType;
use App\Models\AccountAction;
use App\Models\Alert;
use App\Models\User;
use App\Services\Accounts\AccountActionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Analyst-facing protective actions on an alert. Block/OTP are applied immediately (and recorded as account actions);
 * freeze / unfreeze requests go through four-eyes approval and stay pending until a supervisor decides.
 */
class AlertActionService
{
    public function __construct(
        private readonly AccountActionService $actions,
        private readonly AlertInsights $insights,
    ) {}

    public function perform(Alert $alert, AccountActionType $type, string $reason, User $user): AccountAction
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('analyst.errors.reason_required'));
        }

        $permission = match ($type) {
            AccountActionType::UnfreezeAccount => 'accounts.unfreeze',
            AccountActionType::FreezeAccount, AccountActionType::FreezeBeneficiary => 'accounts.freeze',
            default => 'alerts.decide',
        };
        if (! $user->hasPermission($permission)) {
            throw new AuthorizationException(__('common.forbidden'));
        }

        $account = $this->insights->account($alert) ?? throw new InvalidArgumentException(__('analyst.errors.proposal_no_account'));
        $transaction = $alert->riskScore?->transaction;

        $context = [
            'alert_id' => $alert->id,
            'transaction' => $transaction,
            'beneficiary' => $type === AccountActionType::FreezeBeneficiary ? $transaction?->beneficiary : null,
            'reason' => $reason,
            'requested_by' => $user,
        ];

        if (in_array($type, [AccountActionType::BlockTransaction, AccountActionType::FreezeBeneficiary], true) && ! $transaction) {
            throw new InvalidArgumentException(__('analyst.errors.no_transaction'));
        }
        if ($type === AccountActionType::FreezeBeneficiary && ! $transaction?->beneficiary) {
            throw new InvalidArgumentException(__('analyst.errors.no_beneficiary'));
        }

        if (in_array($type, [AccountActionType::FreezeAccount, AccountActionType::UnfreezeAccount, AccountActionType::FreezeBeneficiary], true)) {
            if ($this->pendingFor($alert, $type)->exists()) {
                throw new InvalidArgumentException(__('analyst.errors.already_pending'));
            }

            return $this->actions->requestWithApproval($type, $account, $user, $reason, $context);
        }

        return $this->actions->execute($type, $account, $context);
    }

    /**
     * Actions raised from this alert (or against its account) still waiting for a supervisor.
     *
     * @return Builder<AccountAction>
     */
    public function pendingFor(Alert $alert, ?AccountActionType $type = null): Builder
    {
        return AccountAction::query()
            ->where('status', AccountActionStatus::PendingApproval)
            ->where(fn ($q) => $q->where('alert_id', $alert->id)->orWhere('account_id', $this->insights->account($alert)?->id))
            ->when($type, fn ($q) => $q->where('action', $type));
    }
}
