<?php

namespace App\Livewire\Admin;

use App\Enums\ModelStatus;
use App\Jobs\ReloadAiChampion;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\MlModel;
use App\Models\ModelRun;
use App\Services\Audit\AuditLogger;
use App\Support\ModelScore;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Read-only model registry (admin.models / supervisor.models). Training happens outside the system (plan section 0).
 */
class ModelRegistry extends Component
{
    use AuthorizesPermission;

    public ?string $flash = null;

    public function mount(): void
    {
        $this->authorizePermission('models.view');
    }

    public function promote(int $id, AuditLogger $audit): void
    {
        $this->authorizePermission('models.promote');

        DB::transaction(function () use ($id, $audit) {
            $model = MlModel::lockForUpdate()->findOrFail($id);
            $previous = MlModel::where('status', ModelStatus::Champion)->where('id', '!=', $model->id)->get();

            foreach ($previous as $old) {
                $old->update(['status' => ModelStatus::Shadow]);
            }

            $from = $model->status;
            $model->update(['status' => ModelStatus::Champion]);

            $audit->log('model.promoted', $model, ['status' => $from->value, 'previous_champions' => $previous->pluck('id')->all()], [
                'status' => ModelStatus::Champion->value,
                'demoted_to_shadow' => $previous->pluck('id')->all(),
            ]);
        });

        if (config('fraud.ai.driver') === 'http') {
            ReloadAiChampion::dispatch($id);
        }

        $this->flash = __('admin.models.promoted');
    }

    public function setStatus(int $id, string $status, AuditLogger $audit): void
    {
        $this->authorizePermission('models.promote');

        $target = ModelStatus::from($status);
        abort_if($target === ModelStatus::Champion, 422);

        $model = MlModel::findOrFail($id);
        abort_if($model->status === ModelStatus::Champion, 422, __('admin.models.champion_locked'));

        $from = $model->status;
        $model->update(['status' => $target]);
        $audit->log('model.status_changed', $model, ['status' => $from->value], ['status' => $target->value]);
        $this->flash = __('admin.models.status_updated');
    }

    public function render(): View
    {
        $models = MlModel::query()->with(['runs' => fn ($q) => $q->latest('trained_at')->latest('id')])->orderBy('id')->get();

        $runs = ModelRun::query()->with('model')->latest('trained_at')->latest('id')->limit(100)->get()
            ->map(fn (ModelRun $run) => [
                'run' => $run,
                'metrics' => ModelScore::normalise($run->metrics),
                'final' => ModelScore::final($run->metrics),
                'excluded' => ModelScore::excludedFromRealtime($run->metrics),
            ]);

        $drift = $runs->filter(fn (array $row) => filter_var($row['run']->metrics['drift_detected'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->map(fn (array $row) => [
                'model' => $row['run']->model?->model_name,
                'version' => $row['run']->version_number,
                'psi_max' => is_numeric($row['run']->metrics['psi_max'] ?? null) ? round((float) $row['run']->metrics['psi_max'], 3) : null,
                'date' => $row['run']->trained_at,
            ])->values();

        // Comparison chart: latest run of each model.
        $labels = [];
        $series = [];
        foreach (ModelScore::WEIGHTS as $metric => $weight) {
            $series[$metric] = [];
        }
        $series['final'] = [];
        foreach ($models as $model) {
            $run = $model->runs->first();
            $labels[] = $model->model_name.($run ? ' '.$run->version_number : '');
            $normalised = ModelScore::normalise($run?->metrics);
            foreach (ModelScore::WEIGHTS as $metric => $weight) {
                $series[$metric][] = $normalised[$metric] === null ? null : ($metric === 'p95_latency_ms' ? $normalised[$metric] : round($normalised[$metric] * 100, 2));
            }
            $series['final'][] = $run ? ModelScore::final($run->metrics) : null;
        }

        $chart = [
            'labels' => $labels,
            'series' => collect(['final' => __('admin.models.final_score')] + collect(ModelScore::WEIGHTS)->keys()
                ->reject(fn ($m) => $m === 'p95_latency_ms')->mapWithKeys(fn ($m) => [$m => __('admin.models.metrics.'.$m)])->all())
                ->map(fn ($name, $key) => ['name' => $name, 'data' => $series[$key]])->values()->all(),
        ];

        return view('livewire.admin.model-registry', [
            'models' => $models,
            'runs' => $runs,
            'drift' => $drift,
            'chart' => $chart,
            'metricKeys' => ModelScore::allKeys(),
            'weights' => ModelScore::WEIGHTS,
            'canPromote' => auth()->user()?->hasPermission('models.promote'),
        ])->title(__('admin.models.title'));
    }
}
