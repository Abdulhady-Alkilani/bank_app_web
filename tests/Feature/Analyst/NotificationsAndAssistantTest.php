<?php

use App\Enums\AccountActionStatus;
use App\Enums\AccountActionType;
use App\Enums\AiProposalStatus;
use App\Livewire\Analyst\Assistant;
use App\Livewire\Analyst\RuleSuggestions;
use App\Livewire\NotificationBell;
use App\Models\AccountAction;
use App\Models\AiProposal;
use App\Models\AuditLog;
use App\Models\InvestigationCase;
use App\Models\RuleSuggestion;
use App\Models\User;
use App\Services\Investigation\CaseService;
use Illuminate\Support\Str;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

function pushNotification(User $user, array $data): void
{
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\Test',
        'data' => $data,
    ]);
}

it('shows unread notifications, links alerts and marks them read', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    pushNotification($analyst, ['alert_id' => $alert->id, 'score' => 91, 'message' => 'High risk alert']);
    pushNotification($analyst, ['message' => 'Model drift detected', 'url' => '/admin/models']);

    $component = Livewire::actingAs($analyst)->test(NotificationBell::class)
        ->assertSee('High risk alert')
        ->assertSee('Model drift detected')
        ->assertSeeHtml('data-testid="unread-count"')
        ->assertViewHas('unread', 2);

    $id = $analyst->notifications()->where('data->alert_id', $alert->id)->value('id') ?? $analyst->notifications()->first()->id;
    $component->call('openNotification', $id)->assertRedirect();

    expect($analyst->unreadNotifications()->count())->toBe(1);

    $component->call('markAllRead');
    expect($analyst->unreadNotifications()->count())->toBe(0);
});

it('does not touch other users notifications', function () {
    $analyst = User::factory()->analyst()->create();
    $other = User::factory()->analyst()->create();
    pushNotification($other, ['message' => 'secret']);

    Livewire::actingAs($analyst)->test(NotificationBell::class)
        ->assertDontSee('secret')
        ->call('openNotification', $other->notifications()->first()->id);

    expect($other->unreadNotifications()->count())->toBe(1);
});

it('approves an AI note proposal into an AI-generated case note', function () {
    $analyst = User::factory()->analyst()->create();
    $case = app(CaseService::class)->openFromAlert(makeAnalystAlert(), $analyst);
    $proposal = AiProposal::factory()->create(['case_id' => $case->id, 'tool_name' => 'add_case_note', 'payload' => ['note' => 'Pattern matches mule ring']]);

    Livewire::actingAs($analyst)->test(Assistant::class)
        ->assertSee('add_case_note')
        ->call('approve', $proposal->id);

    $note = $case->notes()->first();
    expect($note->note)->toBe('Pattern matches mule ring')
        ->and($note->is_ai_generated)->toBeTrue()
        ->and($proposal->fresh()->status)->toBe(AiProposalStatus::Approved)
        ->and($proposal->fresh()->reviewed_by)->toBe($analyst->id)
        ->and(AuditLog::where('action', 'ai_proposal.approved')->count())->toBe(1);
});

it('turns an approved propose_action into a four-eyes freeze request', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    $case = app(CaseService::class)->openFromAlert($alert, $analyst);
    $account = $alert->riskScore->transaction->account;
    $proposal = AiProposal::factory()->create([
        'case_id' => $case->id,
        'alert_id' => $alert->id,
        'tool_name' => 'propose_action',
        'payload' => ['action' => 'freeze_account', 'account_id' => $account->id, 'reason' => 'AI suspects takeover'],
    ]);

    Livewire::actingAs($analyst)->test(Assistant::class)->call('approve', $proposal->id);

    $action = AccountAction::first();
    expect($action->action)->toBe(AccountActionType::FreezeAccount)
        ->and($action->status)->toBe(AccountActionStatus::PendingApproval)
        ->and($action->requested_by)->toBe($analyst->id);
});

it('rejects proposals without side effects and hides other analysts proposals', function () {
    $analyst = User::factory()->analyst()->create();
    $case = app(CaseService::class)->openFromAlert(makeAnalystAlert(), $analyst);
    $mine = AiProposal::factory()->create(['case_id' => $case->id]);
    $foreignCase = InvestigationCase::factory()->create(['assigned_to' => User::factory()->analyst()->create()->id]);
    $foreign = AiProposal::factory()->create(['case_id' => $foreignCase->id, 'payload' => ['note' => 'not yours']]);

    Livewire::actingAs($analyst)->test(Assistant::class)
        ->assertDontSee('not yours')
        ->call('reject', $mine->id);

    expect($mine->fresh()->status)->toBe(AiProposalStatus::Rejected)
        ->and($case->notes()->count())->toBe(0);

    Livewire::actingAs($analyst)->test(Assistant::class)->call('approve', $foreign->id)->assertNotFound();

    expect($foreign->fresh()->status)->toBe(AiProposalStatus::Pending)
        ->and($foreignCase->notes()->count())->toBe(0);
});

it('submits a rule suggestion with conditions JSON', function () {
    $analyst = User::factory()->analyst()->create();

    Livewire::actingAs($analyst)->test(RuleSuggestions::class)
        ->set('title', 'Night transfers to new beneficiary')
        ->set('eventTypes', ['transfer'])
        ->set('conditions', [
            ['feature' => 'amount_to_avg_ratio', 'op' => '>=', 'value' => '10'],
            ['feature' => 'hour_of_day', 'op' => 'in', 'value' => '1, 2, 3'],
        ])
        ->call('submit')
        ->assertHasNoErrors();

    $s = RuleSuggestion::first();
    expect($s->suggested_by)->toBe($analyst->id)
        ->and($s->conditions)->toBe([
            'event_types' => ['transfer'],
            'all' => [
                ['feature' => 'amount_to_avg_ratio', 'op' => '>=', 'value' => 10],
                ['feature' => 'hour_of_day', 'op' => 'in', 'value' => [1, 2, 3]],
            ],
        ]);

    Livewire::actingAs($analyst)->test(RuleSuggestions::class)
        ->set('title', 'x')
        ->set('conditions', [['feature' => 'bogus', 'op' => '>', 'value' => '1']])
        ->call('submit')
        ->assertHasErrors('conditions.0.feature');
});
