<?php

namespace App\Mcp\Tools;

use App\Enums\AiProposalStatus;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpToolException;
use App\Models\AiProposal;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('add_case_note')]
#[Title('Propose a case note')]
#[Description('Creates a PROPOSAL for a case note. Nothing is written to the case until a human analyst approves it. Provide case_id, or alert_id of an alert that already belongs to a case. Requires a token with mcp:propose. اقتراح ملاحظة على حالة (بانتظار موافقة المحلل).')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class AddCaseNoteTool extends FraudTool
{
    protected string $ability = McpAccess::PROPOSE;

    public function schema(JsonSchema $schema): array
    {
        return [
            'note' => $schema->string()->description('Note text (max 2000 chars)')->min(3)->max(2000)->required(),
            'case_id' => $schema->integer()->description('Investigation case id'),
            'alert_id' => $schema->integer()->description('Alert id (its case is used when case_id is omitted)'),
        ];
    }

    protected function execute(Request $request, User $user): array
    {
        $data = $request->validate([
            'note' => 'required|string|min:3|max:2000',
            'case_id' => 'nullable|integer|min:1|required_without:alert_id',
            'alert_id' => 'nullable|integer|min:1|required_without:case_id',
        ]);

        $alert = isset($data['alert_id']) ? McpAccess::alertFor($user, $data['alert_id']) : null;
        $caseId = $data['case_id'] ?? $alert?->case_id;

        if (! $caseId) {
            throw new McpToolException(McpAccess::bilingual(
                'The alert has no investigation case yet; ask the analyst to open one or provide case_id.',
                'لا توجد حالة تحقيق لهذا التنبيه بعد؛ اطلب من المحلل فتح حالة أو حدد case_id.',
            ));
        }

        $case = McpAccess::caseFor($user, $caseId);

        $proposal = AiProposal::create([
            'case_id' => $case->id,
            'alert_id' => $alert?->id,
            'tool_name' => 'add_case_note',
            'payload' => ['note' => $data['note']],
            'status' => AiProposalStatus::Pending,
        ]);

        return [
            'proposal_id' => $proposal->id,
            'status' => 'pending',
            'case_id' => $case->id,
            'message' => "proposal #{$proposal->id} awaiting analyst approval / الاقتراح رقم {$proposal->id} بانتظار موافقة المحلل",
        ];
    }
}
