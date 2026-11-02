<?php

namespace App\Mcp\Tools;

use App\Enums\AccountActionType;
use App\Enums\AiProposalStatus;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpToolException;
use App\Models\AiProposal;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('propose_action')]
#[Title('Propose an account action')]
#[Description('Creates a PROPOSAL for a protective action (block_transaction, require_otp, freeze_account, unfreeze_account, freeze_beneficiary). It is NEVER executed by this server: a human analyst must approve it, and freezes still need four-eyes approval. Requires a token with mcp:propose. اقتراح إجراء (لا ينفذ دون موافقة المحلل).')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ProposeActionTool extends FraudTool
{
    protected string $ability = McpAccess::PROPOSE;

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(array_map(fn (AccountActionType $t) => $t->value, AccountActionType::cases()))->description('Proposed action')->required(),
            'reason' => $schema->string()->description('Evidence-based justification (max 1000 chars)')->min(5)->max(1000)->required(),
            'alert_id' => $schema->integer()->description('Related alert (also resolves the account)'),
            'account_id' => $schema->integer()->description('Account id'),
            'account_number' => $schema->string()->description('Full account number'),
            'transaction_id' => $schema->integer()->description('Transaction to block (block_transaction)'),
            'beneficiary_id' => $schema->integer()->description('Beneficiary to freeze (freeze_beneficiary)'),
        ];
    }

    protected function execute(Request $request, User $user): array
    {
        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(array_map(fn (AccountActionType $t) => $t->value, AccountActionType::cases()))],
            'reason' => 'required|string|min:5|max:1000',
            'alert_id' => 'nullable|integer|min:1',
            'account_id' => 'nullable|integer|min:1',
            'account_number' => 'nullable|string|max:64',
            'transaction_id' => 'nullable|integer|min:1',
            'beneficiary_id' => 'nullable|integer|min:1',
        ]);

        $account = McpAccess::accountFor($user, $data);
        $action = AccountActionType::from($data['action']);

        if ($action === AccountActionType::BlockTransaction) {
            if (empty($data['transaction_id']) || ! $account->transactions()->whereKey($data['transaction_id'])->exists()) {
                throw new McpToolException(McpAccess::bilingual('block_transaction needs a transaction_id belonging to the account.', 'يتطلب block_transaction معرّف معاملة تخص الحساب.'));
            }
        }

        if ($action === AccountActionType::FreezeBeneficiary) {
            if (empty($data['beneficiary_id']) || ! $account->beneficiaries()->whereKey($data['beneficiary_id'])->exists()) {
                throw new McpToolException(McpAccess::bilingual('freeze_beneficiary needs a beneficiary_id belonging to the account.', 'يتطلب freeze_beneficiary معرّف مستفيد يخص الحساب.'));
            }
        }

        $alert = isset($data['alert_id']) ? McpAccess::alertFor($user, $data['alert_id']) : null;

        $proposal = AiProposal::create([
            'case_id' => $alert?->case_id,
            'alert_id' => $alert?->id,
            'tool_name' => 'propose_action',
            'payload' => array_filter([
                'action' => $action->value,
                'account_id' => $account->id,
                'reason' => $data['reason'],
                'transaction_id' => $data['transaction_id'] ?? null,
                'beneficiary_id' => $data['beneficiary_id'] ?? null,
            ], fn ($value) => $value !== null),
            'status' => AiProposalStatus::Pending,
        ]);

        return [
            'proposal_id' => $proposal->id,
            'status' => 'pending',
            'action' => $action->value,
            'message' => "proposal #{$proposal->id} awaiting analyst approval / الاقتراح رقم {$proposal->id} بانتظار موافقة المحلل",
        ];
    }
}
