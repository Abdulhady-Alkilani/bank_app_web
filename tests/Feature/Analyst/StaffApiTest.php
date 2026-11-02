<?php

use App\Enums\AlertStatus;
use App\Enums\AnalystDecision;
use App\Models\Label;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/helpers.php';

it('requires authentication', function () {
    $this->getJson('/api/v1/alerts')->assertUnauthorized();
});

it('lists alerts for staff, scoped to what they may see, with filters', function () {
    $analyst = User::factory()->analyst()->create();
    $other = User::factory()->analyst()->create();
    $visible = makeAnalystAlert(['assigned_to' => $analyst->id], 85);
    makeAnalystAlert(['assigned_to' => $other->id], 90);

    Sanctum::actingAs($analyst);

    $this->getJson('/api/v1/alerts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->id)
        ->assertJsonPath('data.0.score', 85);

    $this->getJson('/api/v1/alerts?min_score=90')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/alerts?priority=nope')->assertUnprocessable();
});

it('shows an alert with reasons and features and hides foreign ones', function () {
    $analyst = User::factory()->analyst()->create();
    $mine = makeAnalystAlert(['assigned_to' => $analyst->id]);
    $foreign = makeAnalystAlert(['assigned_to' => User::factory()->analyst()->create()->id]);

    Sanctum::actingAs($analyst);

    $this->getJson("/api/v1/alerts/{$mine->id}")
        ->assertOk()
        ->assertJsonPath('data.reasons.0.text', 'New device used')
        ->assertJsonPath('data.features.is_new_device', true);

    $this->getJson("/api/v1/alerts/{$foreign->id}")->assertForbidden();
});

it('records a decision through the API with label and validation', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    Sanctum::actingAs($analyst);

    $this->postJson("/api/v1/alerts/{$alert->id}/decision", ['decision' => 'confirmed_fraud'])->assertUnprocessable();
    $this->postJson("/api/v1/alerts/{$alert->id}/decision", ['decision' => 'bogus', 'reason' => 'abc'])->assertUnprocessable();

    $this->postJson("/api/v1/alerts/{$alert->id}/decision", ['decision' => 'confirmed_fraud', 'reason' => 'Verified by phone'])
        ->assertOk()
        ->assertJsonPath('data.analyst_decision', 'confirmed_fraud')
        ->assertJsonPath('data.status', 'closed');

    expect($alert->fresh()->decision)->toBe(AnalystDecision::ConfirmedFraud)
        ->and($alert->fresh()->status)->toBe(AlertStatus::Closed)
        ->and(Label::first()->is_fraud)->toBeTrue();
});

it('denies customers and returns the account network', function () {
    $alert = makeAnalystAlert();
    $account = $alert->riskScore->transaction->account;

    Sanctum::actingAs(User::factory()->customer()->create());
    $this->getJson('/api/v1/alerts')->assertForbidden();
    $this->getJson("/api/v1/accounts/{$account->id}/network")->assertForbidden();

    Sanctum::actingAs(User::factory()->analyst()->create());
    $this->getJson("/api/v1/accounts/{$account->id}/network?depth=2")
        ->assertOk()
        ->assertJsonStructure(['data' => ['nodes', 'edges']]);
    $this->getJson("/api/v1/accounts/{$account->id}/network?depth=7")->assertUnprocessable();
});
