<?php

use App\Enums\ModelStatus;
use App\Livewire\Admin\AuditLog as AuditLogComponent;
use App\Livewire\Admin\ModelRegistry;
use App\Models\AuditLog;
use App\Models\MlModel;
use App\Models\ModelRun;
use App\Models\User;
use App\Support\ModelScore;
use Livewire\Livewire;

it('promotes a model to champion and demotes the previous champion to shadow', function () {
    $admin = User::factory()->admin()->create();
    $old = MlModel::factory()->champion()->create();
    $new = MlModel::factory()->create();

    Livewire::actingAs($admin)->test(ModelRegistry::class)->call('promote', $new->id);

    expect($new->fresh()->status)->toBe(ModelStatus::Champion)
        ->and($old->fresh()->status)->toBe(ModelStatus::Shadow)
        ->and(MlModel::where('status', 'champion')->count())->toBe(1);

    $log = AuditLog::where('action', 'model.promoted')->first();
    expect($log->new_values['demoted_to_shadow'])->toBe([$old->id]);
});

it('forbids promotion for supervisors but allows viewing', function () {
    $supervisor = User::factory()->supervisor()->create();
    $model = MlModel::factory()->create();

    Livewire::actingAs($supervisor)->test(ModelRegistry::class)->assertOk()
        ->call('promote', $model->id)->assertForbidden();

    expect($model->fresh()->status)->toBe(ModelStatus::Candidate);
});

it('sets shadow and archive states but protects the champion', function () {
    $admin = User::factory()->admin()->create();
    $champion = MlModel::factory()->champion()->create();
    $candidate = MlModel::factory()->create();

    $component = Livewire::actingAs($admin)->test(ModelRegistry::class)
        ->call('setStatus', $candidate->id, 'shadow');
    expect($candidate->fresh()->status)->toBe(ModelStatus::Shadow);

    $component->call('setStatus', $candidate->id, 'archived');
    expect($candidate->fresh()->status)->toBe(ModelStatus::Archived);

    $component->call('setStatus', $champion->id, 'archived')->assertStatus(422);
});

it('computes the weighted final score from available metrics', function () {
    expect(ModelScore::final(null))->toBeNull()
        ->and(ModelScore::final(['pr_auc' => 1.0, 'recall_at_p90' => 1.0, 'precision_at_k' => 1.0, 'interpretability' => 1.0, 'resource_score' => 1.0, 'p95_latency_ms' => 0, 'stability' => 1.0, 'f1' => 0.1]))->toBe(100.0)
        ->and(ModelScore::final(['auc' => 0.5]))->toBeNull()
        ->and(ModelScore::final(['recall' => 0.5]))->toBe(50.0);
});

it('uses the appendix weights, derives stability and flags slow models', function () {
    expect(array_sum(ModelScore::WEIGHTS))->toBe(100)
        ->and(ModelScore::WEIGHTS)->toMatchArray(['pr_auc' => 30, 'recall_at_p90' => 20, 'precision_at_k' => 15, 'interpretability' => 10, 'p95_latency_ms' => 10, 'stability' => 10, 'resource_score' => 5]);

    // F1 / F2 / ROC-AUC are displayed but never weighted.
    expect(ModelScore::final(['pr_auc' => 0.8, 'f1' => 0.1, 'f2' => 0.1, 'roc_auc' => 0.1]))->toBe(80.0);

    // Stability derived from pr_auc_std; latency 150ms vs 300ms budget = 0.5.
    expect(ModelScore::normalise(['pr_auc_std' => 0.05])['stability'])->toBe(0.95)
        ->and(ModelScore::final(['pr_auc' => 1.0, 'p95_latency_ms' => 150]))->toBe(round((30 + 5) / 40 * 100, 2));

    expect(ModelScore::excludedFromRealtime(['p95_latency_ms' => 301]))->toBeTrue()
        ->and(ModelScore::excludedFromRealtime(['p95_latency_ms' => 300]))->toBeFalse()
        ->and(ModelScore::excludedFromRealtime(null))->toBeFalse();
});

it('serves the model comparison api for staff with models.view only', function () {
    $run = ModelRun::factory()->create(['metrics' => ['pr_auc' => 0.8, 'f1' => 0.7]]);

    $this->actingAs(User::factory()->supervisor()->create(), 'sanctum')
        ->getJson('/api/v1/models/compare')
        ->assertOk()
        ->assertJsonPath('data.0.id', $run->model_id)
        ->assertJsonPath('data.0.latest_run.metrics.pr_auc', 0.8);

    $this->actingAs(User::factory()->analyst()->create(), 'sanctum')
        ->getJson('/api/v1/models/compare')
        ->assertForbidden();
});

it('exports the audit log as csv and excel for users with audit.export only', function () {
    $admin = User::factory()->admin()->create();
    AuditLog::factory()->count(3)->create(['action' => 'something.done']);

    Livewire::actingAs($admin)->test(AuditLogComponent::class)
        ->set('action', 'something')
        ->call('exportCsv')
        ->assertFileDownloaded();

    Livewire::actingAs($admin)->test(AuditLogComponent::class)->call('exportExcel')->assertFileDownloaded();
    expect(AuditLog::where('action', 'audit.exported')->count())->toBe(2);

    $supervisor = User::factory()->supervisor()->create();
    Livewire::actingAs($supervisor)->test(AuditLogComponent::class)->assertOk()
        ->call('exportCsv')->assertForbidden();
});

it('filters the audit log and shows details', function () {
    $admin = User::factory()->admin()->create();
    $match = AuditLog::factory()->create(['action' => 'user.created', 'old_values' => [], 'new_values' => ['name' => 'Zed']]);
    AuditLog::factory()->create(['action' => 'rule.created']);

    Livewire::actingAs($admin)->test(AuditLogComponent::class)
        ->set('action', 'user.created')
        ->assertSee('user.created')
        ->assertDontSee('rule.created')
        ->call('select', $match->id)
        ->assertSee('Zed');
});

it('forbids non-admin roles from admin pages', function (string $role) {
    $user = User::factory()->withRole($role)->create();

    foreach (['', '/users', '/roles', '/settings', '/integrations', '/rules', '/watchlists', '/models', '/health', '/audit', '/backups'] as $path) {
        $this->actingAs($user)->get('/admin'.$path)->assertForbidden();
    }
})->with(['analyst', 'supervisor', 'customer']);

it('renders every admin page for an admin', function () {
    $admin = User::factory()->admin()->create();

    foreach (['', '/users', '/roles', '/settings', '/integrations', '/rules', '/watchlists', '/models', '/health', '/audit', '/backups'] as $path) {
        $this->actingAs($admin)->get('/admin'.$path)->assertOk();
    }
});

it('shows system health with fallback when the AI client is not bound', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/health')->assertOk();
    $this->actingAs($admin)->get('/admin')->assertOk();
});

it('serves the api docs page to authenticated users only', function () {
    $this->get('/docs/api')->assertRedirect('/login');

    $this->actingAs(User::factory()->analyst()->create())
        ->get('/docs/api')
        ->assertOk()
        ->assertSee('/docs/openapi.yaml', false);

    expect(file_exists(public_path('docs/openapi.yaml')))->toBeTrue();
});
