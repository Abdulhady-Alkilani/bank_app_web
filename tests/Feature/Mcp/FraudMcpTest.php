<?php

use App\Enums\AccountActionType;
use App\Enums\AiProposalStatus;
use App\Enums\ModelStatus;
use App\Mcp\Prompts\InvestigateAlertPrompt;
use App\Mcp\Prompts\SummarizeCasePrompt;
use App\Mcp\Resources\FraudPolicyResource;
use App\Mcp\Resources\InvestigationGuideResource;
use App\Mcp\Resources\RuleDefinitionsResource;
use App\Mcp\Servers\FraudInvestigationServer;
use App\Mcp\Tools\AddCaseNoteTool;
use App\Mcp\Tools\ExplainScoreTool;
use App\Mcp\Tools\FindRelatedAccountsTool;
use App\Mcp\Tools\GetAccountProfileTool;
use App\Mcp\Tools\GetAlertDetailsTool;
use App\Mcp\Tools\GetDeviceLinksTool;
use App\Mcp\Tools\GetModelComparisonTool;
use App\Mcp\Tools\GetTransactionHistoryTool;
use App\Mcp\Tools\ProposeActionTool;
use App\Models\Account;
use App\Models\AccountAction;
use App\Models\AiProposal;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Device;
use App\Models\InvestigationCase;
use App\Models\MlModel;
use App\Models\ModelRun;
use App\Models\Rule;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Investigation\AiProposalService;
use App\Services\Settings\SettingsService;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../Analyst/helpers.php';

function mcpAs(User $user, array $abilities = ['mcp:read']): void
{
    Sanctum::actingAs($user, $abilities);
}

/**
 * An alert on an account with a customer holding recognisable PII.
 *
 * @param  array<string, mixed>  $alert
 */
function mcpAlert(array $alert = [], string $number = '1099887766'): Alert
{
    $customer = Customer::factory()->create(['name' => 'Ahmed Saleh', 'phone' => '+967711223344', 'email' => 'ahmed.saleh@example.com']);
    $account = Account::factory()->create(['customer_id' => $customer->id, 'account_number' => $number]);

    return makeAnalystAlert($alert, 82, ['ip_address' => '203.0.113.77'], $account);
}

it('registers the server with tools, resources and prompts', function () {
    $analyst = User::factory()->analyst()->create();
    mcpAs($analyst);

    FraudInvestigationServer::tools()->assertRegistered([
        GetAlertDetailsTool::class, GetAccountProfileTool::class, GetTransactionHistoryTool::class, GetDeviceLinksTool::class,
        FindRelatedAccountsTool::class, ExplainScoreTool::class, GetModelComparisonTool::class, AddCaseNoteTool::class, ProposeActionTool::class,
    ]);
    FraudInvestigationServer::resources()->assertRegistered([FraudPolicyResource::class, RuleDefinitionsResource::class, InvestigationGuideResource::class]);
    FraudInvestigationServer::prompts()->assertRegistered([InvestigateAlertPrompt::class, SummarizeCasePrompt::class]);
});

it('returns alert details with every sensitive value masked', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = mcpAlert();
    mcpAs($analyst);

    FraudInvestigationServer::tool(GetAlertDetailsTool::class, ['alert_id' => $alert->id])
        ->assertOk()
        ->assertHasNoErrors()
        ->assertSee(['New device used', '10******66', '203.0.*.*'])
        ->assertDontSee(['1099887766', '203.0.113.77', '+967711223344', 'ahmed.saleh@example.com', 'Ahmed Saleh']);
});

it('masks account, phone, email, ip and name in the account profile', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = mcpAlert();
    mcpAs($analyst);

    FraudInvestigationServer::tool(GetAccountProfileTool::class, ['alert_id' => $alert->id])
        ->assertHasNoErrors()
        ->assertSee(['10******66', 'A***', '+967******344'])
        ->assertDontSee(['1099887766', '+967711223344', 'ahmed.saleh@example.com', 'Ahmed Saleh']);
});

it('enforces alert visibility: an analyst cannot read an alert assigned to a colleague', function () {
    $analyst = User::factory()->analyst()->create();
    $colleague = User::factory()->analyst()->create();
    $supervisor = User::factory()->supervisor()->create();

    $foreign = mcpAlert(['assigned_to' => $colleague->id], '1011111111');
    $own = mcpAlert(['assigned_to' => $analyst->id], '1033333333');
    $unassigned = mcpAlert([], '1022222222');

    mcpAs($analyst);
    foreach ([GetAlertDetailsTool::class, ExplainScoreTool::class] as $tool) {
        FraudInvestigationServer::tool($tool, ['alert_id' => $foreign->id])->assertHasErrors(['not found or not accessible']);
        FraudInvestigationServer::tool($tool, ['alert_id' => $own->id])->assertHasNoErrors();
        FraudInvestigationServer::tool($tool, ['alert_id' => $unassigned->id])->assertHasNoErrors();
    }
    FraudInvestigationServer::tool(GetAccountProfileTool::class, ['alert_id' => $foreign->id])->assertHasErrors();
    FraudInvestigationServer::tool(GetAccountProfileTool::class, ['account_id' => $foreign->riskScore->transaction->account_id])->assertHasErrors();
    FraudInvestigationServer::tool(GetTransactionHistoryTool::class, ['alert_id' => $foreign->id])->assertHasErrors();
    FraudInvestigationServer::tool(GetDeviceLinksTool::class, ['alert_id' => $foreign->id])->assertHasErrors();
    FraudInvestigationServer::tool(FindRelatedAccountsTool::class, ['alert_id' => $foreign->id])->assertHasErrors();

    mcpAs($supervisor);
    FraudInvestigationServer::tool(GetAlertDetailsTool::class, ['alert_id' => $foreign->id])->assertHasNoErrors();
});

it('limits transaction history by limit and days', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = mcpAlert();
    $account = $alert->riskScore->transaction->account;
    Transaction::factory()->count(5)->create(['account_id' => $account->id, 'transaction_time' => now()->subDays(2), 'amount' => 11.11]);
    Transaction::factory()->create(['account_id' => $account->id, 'transaction_time' => now()->subDays(60), 'amount' => 99.99]);
    mcpAs($analyst);

    FraudInvestigationServer::tool(GetTransactionHistoryTool::class, ['account_id' => $account->id, 'limit' => 3, 'days' => 30])
        ->assertStructuredContent(fn ($json) => $json->where('count', 3)->where('window_days', 30)->etc());

    FraudInvestigationServer::tool(GetTransactionHistoryTool::class, ['account_id' => $account->id, 'days' => 365, 'limit' => 100])
        ->assertStructuredContent(fn ($json) => $json->where('count', 7)->etc());

    FraudInvestigationServer::tool(GetTransactionHistoryTool::class, ['account_id' => $account->id, 'limit' => 500])->assertHasErrors();
});

it('lists devices and shared devices with masked accounts', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = mcpAlert();
    $account = $alert->riskScore->transaction->account;
    $device = Device::factory()->create(['last_ip' => '198.51.100.9']);
    CustomerDevice::factory()->create(['customer_id' => $account->customer_id, 'device_id' => $device->id]);
    $other = Account::factory()->create(['account_number' => '1055566677']);
    CustomerDevice::factory()->create(['customer_id' => $other->customer_id, 'device_id' => $device->id]);
    mcpAs($analyst);

    FraudInvestigationServer::tool(GetDeviceLinksTool::class, ['account_id' => $account->id])
        ->assertStructuredContent(fn ($json) => $json->where('shared_device_count', 1)->etc())
        ->assertSee(['10******77', '198.51.*.*'])
        ->assertDontSee(['1055566677', '198.51.100.9']);
});

it('finds related accounts up to depth 3 and rejects deeper searches', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = mcpAlert();
    $account = $alert->riskScore->transaction->account;
    $device = Device::factory()->create();
    CustomerDevice::factory()->create(['customer_id' => $account->customer_id, 'device_id' => $device->id]);
    $other = Account::factory()->create(['account_number' => '1077788899']);
    CustomerDevice::factory()->create(['customer_id' => $other->customer_id, 'device_id' => $device->id]);
    mcpAs($analyst);

    FraudInvestigationServer::tool(FindRelatedAccountsTool::class, ['account_id' => $account->id, 'depth' => 2])
        ->assertStructuredContent(fn ($json) => $json->where('related_count', 1)->etc())
        ->assertSee('10******99')
        ->assertDontSee('1077788899');

    FraudInvestigationServer::tool(FindRelatedAccountsTool::class, ['account_id' => $account->id, 'depth' => 4])->assertHasErrors();
});

it('explains a score in Arabic and English using live thresholds', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = mcpAlert();
    app(SettingsService::class)->set('decision.challenge_max', 70);
    mcpAs($analyst);

    FraudInvestigationServer::tool(ExplainScoreTool::class, ['alert_id' => $alert->id])
        ->assertSee(['درجة الخطورة 82', 'Risk score 82/100', 'New device used', '71-100']);
});

it('compares models with their weighted final score', function () {
    $analyst = User::factory()->analyst()->create();
    $model = MlModel::factory()->create(['model_name' => 'xgb-fraud', 'status' => ModelStatus::Champion]);
    ModelRun::factory()->create(['model_id' => $model->id, 'version_number' => '1.0.0', 'metrics' => ['pr_auc' => 0.9, 'f1' => 0.8, 'roc_auc' => 0.95]]);
    mcpAs($analyst);

    FraudInvestigationServer::tool(GetModelComparisonTool::class)
        ->assertHasNoErrors()
        ->assertSee(['xgb-fraud', 'final_score', 'champion']);
});

it('requires the mcp:propose ability for proposal tools', function () {
    $analyst = User::factory()->analyst()->create();
    $case = InvestigationCase::factory()->create();
    mcpAs($analyst, ['mcp:read']);

    FraudInvestigationServer::tool(AddCaseNoteTool::class, ['case_id' => $case->id, 'note' => 'Looks like takeover'])
        ->assertHasErrors(['mcp:propose']);
    FraudInvestigationServer::tool(ProposeActionTool::class, ['action' => 'freeze_account', 'reason' => 'Takeover suspected', 'account_id' => Account::factory()->create()->id])
        ->assertHasErrors(['mcp:propose']);

    expect(AiProposal::count())->toBe(0);
});

it('stores a case note as a pending proposal and applies it only after human approval', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = mcpAlert(['assigned_to' => $analyst->id]);
    $case = InvestigationCase::factory()->create(['assigned_to' => $analyst->id]);
    $alert->update(['case_id' => $case->id]);
    mcpAs($analyst, ['mcp:read', 'mcp:propose']);

    FraudInvestigationServer::tool(AddCaseNoteTool::class, ['alert_id' => $alert->id, 'note' => 'Device is shared with 3 other accounts.'])
        ->assertHasNoErrors()
        ->assertSee('awaiting analyst approval');

    $proposal = AiProposal::first();
    expect($proposal->status)->toBe(AiProposalStatus::Pending)
        ->and($proposal->tool_name)->toBe('add_case_note')
        ->and($proposal->case_id)->toBe($case->id)
        ->and($proposal->payload)->toBe(['note' => 'Device is shared with 3 other accounts.'])
        ->and($case->notes()->count())->toBe(0);

    app(AiProposalService::class)->approve($proposal, $analyst);

    expect($proposal->fresh()->status)->toBe(AiProposalStatus::Approved)
        ->and($case->notes()->where('is_ai_generated', true)->count())->toBe(1);
});

it('rejects a case note without a case and for cases of colleagues', function () {
    $analyst = User::factory()->analyst()->create();
    $colleague = User::factory()->analyst()->create();
    $alert = mcpAlert();
    $foreignCase = InvestigationCase::factory()->create(['assigned_to' => $colleague->id]);
    mcpAs($analyst, ['mcp:read', 'mcp:propose']);

    FraudInvestigationServer::tool(AddCaseNoteTool::class, ['alert_id' => $alert->id, 'note' => 'No case yet'])->assertHasErrors(['no investigation case']);
    FraudInvestigationServer::tool(AddCaseNoteTool::class, ['case_id' => $foreignCase->id, 'note' => 'Sneaky note'])->assertHasErrors(['not found or not accessible']);
    FraudInvestigationServer::tool(AddCaseNoteTool::class, ['note' => 'Missing target'])->assertHasErrors();

    expect(AiProposal::count())->toBe(0);
});

it('stores an action as a pending proposal that never executes directly', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = mcpAlert();
    $account = $alert->riskScore->transaction->account;
    mcpAs($analyst, ['mcp:read', 'mcp:propose']);

    FraudInvestigationServer::tool(ProposeActionTool::class, [
        'action' => 'freeze_account', 'reason' => 'Takeover pattern with new device', 'alert_id' => $alert->id,
    ])->assertHasNoErrors()->assertSee('awaiting analyst approval');

    $proposal = AiProposal::first();
    expect($proposal->status)->toBe(AiProposalStatus::Pending)
        ->and($proposal->tool_name)->toBe('propose_action')
        ->and($proposal->alert_id)->toBe($alert->id)
        ->and($proposal->payload['action'])->toBe('freeze_account')
        ->and($proposal->payload['account_id'])->toBe($account->id)
        ->and(AccountAction::count())->toBe(0)
        ->and($account->fresh()->isFrozen())->toBeFalse();

    // Approval by the human goes through the four-eyes flow, still without freezing immediately.
    app(AiProposalService::class)->approve($proposal, $analyst);
    expect(AccountAction::first()->action)->toBe(AccountActionType::FreezeAccount)
        ->and($account->fresh()->isFrozen())->toBeFalse();
});

it('validates propose_action inputs', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = mcpAlert();
    mcpAs($analyst, ['mcp:read', 'mcp:propose']);

    FraudInvestigationServer::tool(ProposeActionTool::class, ['action' => 'delete_everything', 'reason' => 'Because', 'alert_id' => $alert->id])->assertHasErrors();
    FraudInvestigationServer::tool(ProposeActionTool::class, ['action' => 'block_transaction', 'reason' => 'Suspicious', 'alert_id' => $alert->id])->assertHasErrors(['transaction_id']);
    FraudInvestigationServer::tool(ProposeActionTool::class, ['action' => 'freeze_beneficiary', 'reason' => 'Suspicious', 'alert_id' => $alert->id, 'beneficiary_id' => 999])->assertHasErrors(['beneficiary_id']);
    FraudInvestigationServer::tool(ProposeActionTool::class, ['action' => 'freeze_account', 'reason' => 'x', 'alert_id' => $alert->id])->assertHasErrors();

    expect(AiProposal::count())->toBe(0);
});

it('renders resources with live settings and rules', function () {
    $analyst = User::factory()->analyst()->create();
    app(SettingsService::class)->set('decision.allow_max', 25);
    app(SettingsService::class)->set('decision.challenge_max', 60);
    Rule::factory()->create(['rule_name' => 'Rapid new beneficiary transfer', 'is_active' => true, 'conditions' => ['all' => [['feature' => 'beneficiary_is_new', 'op' => '==', 'value' => true]]]]);
    Rule::factory()->create(['rule_name' => 'Disabled legacy rule', 'is_active' => false]);
    mcpAs($analyst);

    FraudInvestigationServer::resource(FraudPolicyResource::class)->assertSee(['0 - 25', '26 - 60', '61 - 100']);
    FraudInvestigationServer::resource(RuleDefinitionsResource::class)
        ->assertSee(['Rapid new beneficiary transfer', 'beneficiary_is_new'])
        ->assertDontSee('Disabled legacy rule');
    FraudInvestigationServer::resource(InvestigationGuideResource::class)->assertSee(['Account takeover', 'Mule', 'Shared device']);
});

it('renders prompts and refuses invisible alerts and cases', function () {
    $analyst = User::factory()->analyst()->create();
    $colleague = User::factory()->analyst()->create();
    $alert = mcpAlert();
    $foreign = mcpAlert(['assigned_to' => $colleague->id], '1011111111');
    $case = InvestigationCase::factory()->create(['title' => 'Takeover ring']);
    $alert->update(['case_id' => $case->id]);
    $foreignCase = InvestigationCase::factory()->create(['assigned_to' => $colleague->id]);
    mcpAs($analyst);

    FraudInvestigationServer::prompt(InvestigateAlertPrompt::class, ['alert_id' => $alert->id])
        ->assertSee(["alert #{$alert->id}", 'get_alert_details', 'find_related_accounts', 'العربية']);
    FraudInvestigationServer::prompt(InvestigateAlertPrompt::class, ['alert_id' => $foreign->id])->assertHasErrors();

    FraudInvestigationServer::prompt(SummarizeCasePrompt::class, ['case_id' => $case->id])
        ->assertSee(['Takeover ring', (string) $alert->id, 'العربية']);
    FraudInvestigationServer::prompt(SummarizeCasePrompt::class, ['case_id' => $foreignCase->id])->assertHasErrors();
});

it('writes a masked audit entry for every tool, resource and prompt call', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = mcpAlert();
    mcpAs($analyst);

    FraudInvestigationServer::tool(GetAccountProfileTool::class, ['account_number' => '1099887766']);
    FraudInvestigationServer::resource(FraudPolicyResource::class);
    FraudInvestigationServer::prompt(InvestigateAlertPrompt::class, ['alert_id' => $alert->id]);

    $tool = AuditLog::where('action', 'mcp.tool_called')->latest('id')->first();
    expect($tool->user_id)->toBe($analyst->id)
        ->and($tool->new_values['primitive'])->toBe('get_account_profile')
        ->and($tool->new_values['status'])->toBe('ok')
        ->and($tool->new_values['arguments']['account_number'])->toBe('10******66')
        ->and(json_encode($tool->new_values))->not->toContain('1099887766')
        ->and(AuditLog::where('action', 'mcp.resource_read')->count())->toBe(1)
        ->and(AuditLog::where('action', 'mcp.prompt_requested')->count())->toBe(1);
});

it('audits denied and failed calls too', function () {
    $analyst = User::factory()->analyst()->create();
    $colleague = User::factory()->analyst()->create();
    $foreign = mcpAlert(['assigned_to' => $colleague->id], '1011111111');
    mcpAs($analyst);

    FraudInvestigationServer::tool(GetAlertDetailsTool::class, ['alert_id' => $foreign->id])->assertHasErrors();
    FraudInvestigationServer::tool(GetAlertDetailsTool::class, ['alert_id' => 'abc'])->assertHasErrors();

    expect(AuditLog::where('action', 'mcp.tool_called')->pluck('new_values')->pluck('status')->all())->toBe(['denied', 'invalid']);
});

it('denies tool calls for users without the mcp.use permission or inactive users', function () {
    $alert = mcpAlert();
    $customer = User::factory()->customer()->create();
    $inactive = User::factory()->analyst()->inactive()->create();

    foreach ([$customer, $inactive] as $user) {
        mcpAs($user);
        FraudInvestigationServer::tool(GetAlertDetailsTool::class, ['alert_id' => $alert->id])->assertHasErrors(['Not authorised']);
    }
});

describe('HTTP endpoint', function () {
    $post = fn (?string $token, string $method = 'tools/list') => test()->postJson('/mcp/fraud', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => []], array_filter([
        'Authorization' => $token ? 'Bearer '.$token : null,
        'Accept' => 'application/json, text/event-stream',
    ]));

    it('rejects a missing or invalid token', function () use ($post) {
        $post(null)->assertUnauthorized();
        $post('999|not-a-real-token')->assertUnauthorized();
    });

    it('rejects tokens without mcp:read', function () use ($post) {
        $analyst = User::factory()->analyst()->create();
        $token = $analyst->createToken('no-mcp', ['alerts:read'])->plainTextToken;

        $post($token)->assertForbidden();
    });

    it('rejects customers and inactive staff even with a valid token', function () use ($post) {
        $customer = User::factory()->customer()->create();
        $inactive = User::factory()->analyst()->inactive()->create();

        $post($customer->createToken('c', ['mcp:read'])->plainTextToken)->assertForbidden();
        $post($inactive->createToken('i', ['mcp:read'])->plainTextToken)->assertForbidden();
    });

    it('serves tools to a valid read token and executes a tool call', function () use ($post) {
        $analyst = User::factory()->analyst()->create();
        $alert = mcpAlert();
        $token = $analyst->createToken('claude', ['mcp:read'])->plainTextToken;

        $response = $post($token);
        $response->assertOk();
        $names = collect($response->json('result.tools'))->pluck('name')->all();
        expect($names)->toContain('get_alert_details', 'explain_score', 'add_case_note', 'propose_action');

        $call = test()->postJson('/mcp/fraud', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
            'params' => ['name' => 'get_alert_details', 'arguments' => ['alert_id' => $alert->id]],
        ], ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json, text/event-stream']);

        $call->assertOk();
        expect($call->getContent())->toContain('10******66')->not->toContain('1099887766');
        expect(AuditLog::where('action', 'mcp.tool_called')->where('user_id', $analyst->id)->exists())->toBeTrue();
    });

    it('blocks proposals over HTTP for a read-only token', function () {
        $analyst = User::factory()->analyst()->create();
        $case = InvestigationCase::factory()->create();
        $token = $analyst->createToken('claude', ['mcp:read'])->plainTextToken;

        $call = test()->postJson('/mcp/fraud', [
            'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
            'params' => ['name' => 'add_case_note', 'arguments' => ['case_id' => $case->id, 'note' => 'Should not be stored']],
        ], ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json, text/event-stream']);

        expect($call->json('result.isError'))->toBeTrue()
            ->and(AiProposal::count())->toBe(0);
    });
});
