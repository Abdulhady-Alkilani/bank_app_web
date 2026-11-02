<?php

use App\Enums\ModelStatus;
use App\Models\MlModel;
use App\Models\ModelRun;
use App\Models\User;
use App\Notifications\ModelDriftNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['fraud.ml_ingest_token' => 'svc-token']);
    $this->body = [
        'model_name' => 'xgb-fraud',
        'algorithm' => 'xgboost',
        'version_number' => '2.1.0',
        'metrics' => ['auc' => 0.95, 'precision' => 0.8, 'recall' => 0.7, 'f1' => 0.75],
        'trained_at' => '2026-10-01 10:00:00',
    ];
});

it('requires the service bearer token', function () {
    $this->postJson('/api/v1/ml/model-runs', $this->body)->assertUnauthorized();
    $this->withToken('wrong')->postJson('/api/v1/ml/model-runs', $this->body)->assertUnauthorized();

    config(['fraud.ml_ingest_token' => null]);
    $this->withToken('')->postJson('/api/v1/ml/model-runs', $this->body)->assertUnauthorized();
});

it('registers a candidate model with its run and metrics', function () {
    $this->withToken('svc-token')->postJson('/api/v1/ml/model-runs', $this->body)
        ->assertCreated()
        ->assertJsonPath('model_name', 'xgb-fraud')
        ->assertJsonPath('model_status', 'candidate')
        ->assertJsonPath('metrics.auc', 0.95)
        ->assertJsonPath('drift_detected', false);

    expect(MlModel::first()->status)->toBe(ModelStatus::Candidate)
        ->and(ModelRun::count())->toBe(1);

    // A second run for the same model reuses the model and keeps its status.
    MlModel::first()->update(['status' => ModelStatus::Champion]);
    $this->withToken('svc-token')->postJson('/api/v1/ml/model-runs', ['version_number' => '2.2.0'] + $this->body)->assertCreated();

    expect(MlModel::count())->toBe(1)->and(MlModel::first()->status)->toBe(ModelStatus::Champion)->and(ModelRun::count())->toBe(2);
});

it('validates the payload', function () {
    $this->withToken('svc-token')->postJson('/api/v1/ml/model-runs', [])
        ->assertStatus(422)->assertJsonValidationErrors(['model_name', 'version_number', 'metrics']);
});

it('notifies active admins when drift is detected', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $inactive = User::factory()->admin()->inactive()->create();
    $analyst = User::factory()->analyst()->create();

    $this->withToken('svc-token')->postJson('/api/v1/ml/model-runs', $this->body)->assertCreated();
    Notification::assertNothingSent();

    $body = $this->body;
    $body['metrics']['drift_detected'] = true;
    $this->withToken('svc-token')->postJson('/api/v1/ml/model-runs', $body)->assertCreated()->assertJsonPath('drift_detected', true);

    Notification::assertSentTo($admin, ModelDriftNotification::class);
    Notification::assertNotSentTo([$inactive, $analyst], ModelDriftNotification::class);
});

it('stores the drift notification for the admin bell', function () {
    $admin = User::factory()->admin()->create();
    $body = $this->body;
    $body['metrics']['drift_detected'] = true;

    $this->withToken('svc-token')->postJson('/api/v1/ml/model-runs', $body)->assertCreated();

    expect($admin->notifications()->first()->data)->toHaveKeys(['model_run_id', 'model_name', 'version', 'message']);
});
