<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MlModel;
use App\Support\ModelScore;
use Illuminate\Http\JsonResponse;

class ModelComparisonController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $data = MlModel::query()->with(['runs' => fn ($q) => $q->latest('trained_at')->latest('id')])->get()
            ->map(function (MlModel $model) {
                $run = $model->runs->first();

                return [
                    'id' => $model->id,
                    'name' => $model->model_name,
                    'algorithm' => $model->algorithm,
                    'status' => $model->status->value,
                    'latest_run' => $run ? [
                        'id' => $run->id,
                        'version' => $run->version_number,
                        'trained_at' => $run->trained_at?->toIso8601String(),
                        'metrics' => ModelScore::normalise($run->metrics),
                        'final_score' => ModelScore::final($run->metrics),
                    ] : null,
                ];
            })->values();

        return response()->json(['data' => $data, 'weights' => ModelScore::WEIGHTS]);
    }
}
