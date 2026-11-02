<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\InvestigateAlertPrompt;
use App\Mcp\Prompts\SummarizeCasePrompt;
use App\Mcp\Resources\FraudPolicyResource;
use App\Mcp\Resources\InvestigationGuideResource;
use App\Mcp\Resources\RuleDefinitionsResource;
use App\Mcp\Tools\AddCaseNoteTool;
use App\Mcp\Tools\ExplainScoreTool;
use App\Mcp\Tools\FindRelatedAccountsTool;
use App\Mcp\Tools\GetAccountProfileTool;
use App\Mcp\Tools\GetAlertDetailsTool;
use App\Mcp\Tools\GetDeviceLinksTool;
use App\Mcp\Tools\GetModelComparisonTool;
use App\Mcp\Tools\GetTransactionHistoryTool;
use App\Mcp\Tools\ProposeActionTool;
use Laravel\Mcp\Server;

class FraudInvestigationServer extends Server
{
    protected string $name = 'Fraud Investigation Server';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'TEXT'
        خادم تحقيق الاحتيال المصرفي: يتيح للمساعد الذكي قراءة بيانات التنبيهات والحسابات (مقنّعة) واقتراح ملاحظات وإجراءات فقط.
        لا ينفذ هذا الخادم أي تجميد أو حظر؛ كل اقتراح يُحفظ بحالة "بانتظار الموافقة" ويقرره المحلل البشري. كل استدعاء يُسجَّل في سجل التدقيق.
        ابدأ بقراءة المورد fraud://investigation-guide ثم استخدم الأدوات: get_alert_details, explain_score, get_account_profile, get_transaction_history, get_device_links, find_related_accounts, get_model_comparison. أدوات الاقتراح (add_case_note, propose_action) تحتاج رمزاً بصلاحية mcp:propose.

        Bank fraud-investigation server: lets an AI assistant read MASKED alert/account data and PROPOSE case notes and actions only.
        This server never freezes or blocks anything; every proposal is stored as "pending" and decided by a human analyst. Every call is written to the audit log.
        Start with the fraud://investigation-guide resource, then use: get_alert_details, explain_score, get_account_profile, get_transaction_history, get_device_links, find_related_accounts, get_model_comparison. Proposal tools (add_case_note, propose_action) require a token with the mcp:propose ability.
    TEXT;

    protected array $tools = [
        GetAlertDetailsTool::class,
        GetAccountProfileTool::class,
        GetTransactionHistoryTool::class,
        GetDeviceLinksTool::class,
        FindRelatedAccountsTool::class,
        ExplainScoreTool::class,
        GetModelComparisonTool::class,
        AddCaseNoteTool::class,
        ProposeActionTool::class,
    ];

    protected array $resources = [
        FraudPolicyResource::class,
        RuleDefinitionsResource::class,
        InvestigationGuideResource::class,
    ];

    protected array $prompts = [
        InvestigateAlertPrompt::class,
        SummarizeCasePrompt::class,
    ];
}
