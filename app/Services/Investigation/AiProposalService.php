<?php

namespace App\Services\Investigation;

use App\Enums\AccountActionType;
use App\Enums\AiProposalStatus;
use App\Models\Account;
use App\Models\AiProposal;
use App\Models\Beneficiary;
use App\Models\User;
use App\Services\Accounts\AccountActionService;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Human review of proposals created by the MCP assistant. The assistant only creates proposals;
 * nothing takes effect until an analyst approves it here.
 *
 * Supported tool_name values:
 *  - add_case_note / propose_case_note: payload {note}  -> case_note (is_ai_generated = true)
 *  - propose_action: payload {action, account_id|account_number, reason, transaction_id?, beneficiary_id?}
 */
class AiProposalService
{
    public function __construct(
        private readonly CaseService $cases,
        private readonly AccountActionService $actions,
        private readonly AuditLogger $audit,
    ) {}

    public function approve(AiProposal $proposal, User $user): AiProposal
    {
        $this->guardPending($proposal);

        return DB::transaction(function () use ($proposal, $user) {
            match ($proposal->tool_name) {
                'add_case_note', 'propose_case_note' => $this->applyNote($proposal, $user),
                'propose_action' => $this->applyAction($proposal, $user),
                default => throw new InvalidArgumentException(__('analyst.errors.unknown_proposal')),
            };

            $proposal->update([
                'status' => AiProposalStatus::Approved,
                'reviewed_by' => $user->getKey(),
                'reviewed_at' => now(),
            ]);

            $this->audit->log('ai_proposal.approved', $proposal, ['status' => 'pending'], ['status' => 'approved', 'tool' => $proposal->tool_name], $user);

            return $proposal->refresh();
        });
    }

    public function reject(AiProposal $proposal, User $user): AiProposal
    {
        $this->guardPending($proposal);

        $proposal->update([
            'status' => AiProposalStatus::Rejected,
            'reviewed_by' => $user->getKey(),
            'reviewed_at' => now(),
        ]);

        $this->audit->log('ai_proposal.rejected', $proposal, ['status' => 'pending'], ['status' => 'rejected', 'tool' => $proposal->tool_name], $user);

        return $proposal->refresh();
    }

    private function guardPending(AiProposal $proposal): void
    {
        if ($proposal->status !== AiProposalStatus::Pending) {
            throw new InvalidArgumentException(__('analyst.errors.proposal_not_pending'));
        }
    }

    private function applyNote(AiProposal $proposal, User $user): void
    {
        $case = $proposal->case ?? $proposal->alert?->case;
        if (! $case) {
            throw new InvalidArgumentException(__('analyst.errors.proposal_no_case'));
        }

        $payload = $proposal->payload ?? [];
        $text = $payload['note'] ?? $payload['text'] ?? $payload['content'] ?? '';

        $this->cases->addNote($case, $user, (string) $text, isAi: true);
    }

    private function applyAction(AiProposal $proposal, User $user): void
    {
        $payload = $proposal->payload ?? [];
        $type = AccountActionType::tryFrom((string) ($payload['action'] ?? $payload['action_type'] ?? ''))
            ?? throw new InvalidArgumentException(__('analyst.errors.unknown_proposal'));

        $account = $this->resolveAccount($payload, $proposal);
        $reason = (string) ($payload['reason'] ?? __('analyst.assistant.default_reason'));

        $permission = match ($type) {
            AccountActionType::UnfreezeAccount => 'accounts.unfreeze',
            AccountActionType::FreezeAccount, AccountActionType::FreezeBeneficiary => 'accounts.freeze',
            default => 'alerts.decide',
        };
        if (! $user->hasPermission($permission)) {
            throw new AuthorizationException(__('common.forbidden'));
        }

        $context = [
            'alert_id' => $proposal->alert_id ?? ($payload['alert_id'] ?? null),
            'transaction' => $payload['transaction_id'] ?? null,
            'beneficiary' => isset($payload['beneficiary_id']) ? Beneficiary::query()->find($payload['beneficiary_id']) : null,
            'reason' => $reason,
            'requested_by' => $user,
        ];

        if (in_array($type, [AccountActionType::FreezeAccount, AccountActionType::UnfreezeAccount, AccountActionType::FreezeBeneficiary], true)) {
            $this->actions->requestWithApproval($type, $account, $user, $reason, $context);
        } else {
            $this->actions->execute($type, $account, $context);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveAccount(array $payload, AiProposal $proposal): Account
    {
        $account = match (true) {
            isset($payload['account_id']) => Account::query()->find($payload['account_id']),
            isset($payload['account_number']) => Account::query()->where('account_number', $payload['account_number'])->first(),
            default => $proposal->alert ? app(AlertInsights::class)->account($proposal->alert) : null,
        };

        return $account ?? throw new InvalidArgumentException(__('analyst.errors.proposal_no_account'));
    }
}
