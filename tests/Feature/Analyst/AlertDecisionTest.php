<?php

use App\Enums\AlertStatus;
use App\Enums\AnalystDecision;
use App\Enums\LabelSource;
use App\Livewire\Analyst\AlertShow;
use App\Models\AuditLog;
use App\Models\Label;
use App\Models\User;
use App\Services\Investigation\AlertDecisionService;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

it('records a fraud label, closes the alert and audits a confirmed-fraud decision', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();

    $alert = app(AlertDecisionService::class)->decide($alert, AnalystDecision::ConfirmedFraud, 'Customer confirmed theft', $analyst);

    expect($alert->decision)->toBe(AnalystDecision::ConfirmedFraud)
        ->and($alert->status)->toBe(AlertStatus::Closed)
        ->and($alert->decided_by)->toBe($analyst->id)
        ->and($alert->decided_at)->not->toBeNull()
        ->and($alert->decision_reason)->toBe('Customer confirmed theft');

    $label = Label::first();
    expect($label->is_fraud)->toBeTrue()
        ->and($label->source)->toBe(LabelSource::Analyst)
        ->and($label->alert_id)->toBe($alert->id)
        ->and($label->transaction_id)->toBe($alert->riskScore->transaction_id)
        ->and(AuditLog::where('action', 'alert.decided')->where('entity_id', $alert->id)->count())->toBe(1);
});

it('labels false positives as not fraud and does not label escalations', function () {
    $analyst = User::factory()->analyst()->create();
    $service = app(AlertDecisionService::class);

    $service->decide(makeAnalystAlert(), AnalystDecision::FalsePositive, 'Normal behaviour', $analyst);
    expect(Label::first()->is_fraud)->toBeFalse();

    $escalated = $service->decide(makeAnalystAlert(), AnalystDecision::Escalated, 'Need supervisor', $analyst);
    $info = $service->decide(makeAnalystAlert(), AnalystDecision::InfoRequested, 'Call customer', $analyst);

    expect(Label::count())->toBe(1)
        ->and($escalated->status)->toBe(AlertStatus::Escalated)
        ->and($info->status)->toBe(AlertStatus::PendingInfo);
});

it('requires a reason when deciding from the UI', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();

    Livewire::actingAs($analyst)->test(AlertShow::class, ['alert' => $alert])
        ->call('decide', 'confirmed_fraud')
        ->assertHasErrors(['reason' => 'required']);

    expect($alert->fresh()->decision)->toBeNull();

    Livewire::actingAs($analyst)->test(AlertShow::class, ['alert' => $alert])
        ->set('reason', 'Clear account takeover')
        ->call('decide', 'confirmed_fraud')
        ->assertHasNoErrors();

    expect($alert->fresh()->decision)->toBe(AnalystDecision::ConfirmedFraud);
});

it('renders the alert detail with reasons, features and timeline, tolerating odd reason shapes', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    $alert->riskScore->update(['reasons' => [['code' => 'X'], 'plain string reason', ['text' => 'With impact', 'impact' => 12]]]);

    $this->actingAs($analyst)->get(route('analyst.alerts.show', $alert))
        ->assertOk()
        ->assertSee('plain string reason')
        ->assertSee('With impact');
});

it('hides alerts assigned to another analyst', function () {
    $other = User::factory()->analyst()->create();
    $alert = makeAnalystAlert(['assigned_to' => $other->id]);

    $this->actingAs(User::factory()->analyst()->create())->get(route('analyst.alerts.show', $alert))->assertForbidden();
    $this->actingAs(User::factory()->supervisor()->create())->get(route('analyst.alerts.show', $alert))->assertOk();
});
