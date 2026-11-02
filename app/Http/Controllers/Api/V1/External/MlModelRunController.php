<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ModelRunRequest;
use App\Http\Resources\Api\V1\ModelRunResource;
use App\Models\MlModel;
use App\Models\ModelRun;
use App\Models\User;
use App\Notifications\ModelDriftNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class MlModelRunController extends Controller
{
    /**
     * POST /ml/model-runs — registered by the Python training scripts. New models start as `candidate`;
     * promotion to champion stays a manual, audited admin action. Drift (metrics.drift_detected) alerts admins (SYS-09).
     */
    public function store(ModelRunRequest $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validated();

        $model = MlModel::firstOrCreate(
            ['model_name' => $data['model_name']],
            ['algorithm' => $data['algorithm'] ?? null, 'status' => ModelStatus::Candidate],
        );

        $run = ModelRun::create([
            'model_id' => $model->id,
            'version_number' => $data['version_number'],
            'metrics' => (array) $request->input('metrics'),
            'trained_at' => $data['trained_at'] ?? now(),
        ]);

        $audit->log('ml.model_run_ingested', $run, [], [
            'model' => $model->model_name,
            'version' => $run->version_number,
            'metrics' => $run->metrics,
        ]);

        if (filter_var(data_get($run->metrics, 'drift_detected', false), FILTER_VALIDATE_BOOLEAN)) {
            $admins = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))
                ->get();

            Notification::send($admins, new ModelDriftNotification($run->load('model')));
        }

        return (new ModelRunResource($run->load('model')))->response()->setStatusCode(201);
    }
}
