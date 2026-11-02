<?php

use App\Jobs\ReloadAiChampion;
use App\Livewire\Admin\ModelRegistry;
use App\Models\AuditLog;
use App\Models\MlModel;
use App\Models\ModelRun;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('queues a reload when an admin promotes a model and the driver is http', function () {
    Queue::fake();
    config(['fraud.ai.driver' => 'http']);
    $model = MlModel::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test(ModelRegistry::class)->call('promote', $model->id);

    Queue::assertPushed(ReloadAiChampion::class, fn (ReloadAiChampion $job) => $job->mlModelId === $model->id);
});

it('does not queue a reload for other drivers', function () {
    Queue::fake();
    config(['fraud.ai.driver' => 'fake']);
    $model = MlModel::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test(ModelRegistry::class)->call('promote', $model->id);

    Queue::assertNotPushed(ReloadAiChampion::class);
});

it('calls /admin/reload with the admin token and the latest run of the model', function () {
    config(['fraud.ai.url' => 'http://ai.test', 'fraud.ai.admin_token' => 'adm']);
    Http::fake(['ai.test/admin/reload' => Http::response(['status' => 'ok'])]);
    $model = MlModel::factory()->create(['model_name' => 'xgboost-fraud']);
    ModelRun::factory()->create(['model_id' => $model->id, 'version_number' => 'v1', 'trained_at' => now()->subDay()]);
    ModelRun::factory()->create(['model_id' => $model->id, 'version_number' => 'v2', 'trained_at' => now()]);

    (new ReloadAiChampion($model->id))->handle(app(AuditLogger::class));

    Http::assertSent(fn (Request $r) => $r->url() === 'http://ai.test/admin/reload'
        && $r->hasHeader('Authorization', 'Bearer adm')
        && $r->data() === ['model_name' => 'xgboost-fraud', 'model_version' => 'v2']);
    expect(AuditLog::where('action', 'model.reload_requested')->exists())->toBeTrue();
});

it('logs an audit entry when the reload fails and the promotion still succeeds', function () {
    config(['fraud.ai.url' => 'http://ai.test', 'fraud.ai.admin_token' => 'adm', 'fraud.ai.driver' => 'http']);
    Http::fake(['*' => Http::response([], 500)]);
    $model = MlModel::factory()->create();
    ModelRun::factory()->create(['model_id' => $model->id]);

    Livewire::actingAs(User::factory()->admin()->create())->test(ModelRegistry::class)->call('promote', $model->id);

    expect($model->fresh()->status->value)->toBe('champion')
        ->and(AuditLog::where('action', 'model.reload_failed')->exists())->toBeTrue();
});

it('lists drift history with psi_max on the registry page', function () {
    $model = MlModel::factory()->create(['model_name' => 'xgboost-fraud']);
    ModelRun::factory()->create(['model_id' => $model->id, 'version_number' => 'v-drifted', 'metrics' => ['pr_auc' => 0.8, 'drift_detected' => true, 'psi_max' => 0.31234], 'trained_at' => '2026-10-20']);
    ModelRun::factory()->create(['model_id' => $model->id, 'version_number' => 'v-clean', 'metrics' => ['pr_auc' => 0.9, 'drift_detected' => false, 'psi_max' => 0.02], 'trained_at' => '2026-10-25']);

    Livewire::actingAs(User::factory()->admin()->create())->test(ModelRegistry::class)
        ->assertSee(__('admin.models.drift_history'))
        ->assertSee('0.312')->assertSee('2026-10-20')
        ->assertViewHas('drift', fn ($drift) => $drift->count() === 1);
});
