<?php

namespace App\Mcp\Tools;

use App\Models\MlModel;
use App\Models\User;
use App\Support\ModelScore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_model_comparison')]
#[Title('Get model comparison')]
#[Description('Read-only. Compares the registered fraud models (champion, shadow, candidate) using the latest run metrics and the weighted final score. مقارنة النماذج.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class GetModelComparisonTool extends FraudTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'include_archived' => $schema->boolean()->description('Include archived models (default false)')->default(false),
        ];
    }

    protected function execute(Request $request, User $user): array
    {
        $data = $request->validate(['include_archived' => 'nullable|boolean']);

        $models = MlModel::query()
            ->with(['runs' => fn ($q) => $q->latest('trained_at')->latest('id')->limit(1)])
            ->when(! ($data['include_archived'] ?? false), fn ($q) => $q->where('status', '!=', 'archived'))
            ->get()
            ->map(function (MlModel $model) {
                $run = $model->runs->first();

                return [
                    'model_id' => $model->id,
                    'name' => $model->model_name,
                    'algorithm' => $model->algorithm,
                    'status' => $model->status->value,
                    'latest_run' => $run ? [
                        'version' => $run->version_number,
                        'trained_at' => $run->trained_at?->toIso8601String(),
                        'metrics' => ModelScore::normalise($run->metrics),
                        'final_score' => ModelScore::final($run->metrics),
                    ] : null,
                ];
            })
            ->sortByDesc(fn (array $row) => $row['latest_run']['final_score'] ?? -1)
            ->values();

        return [
            'weights' => ModelScore::WEIGHTS,
            'count' => $models->count(),
            'models' => $models->all(),
        ];
    }
}
