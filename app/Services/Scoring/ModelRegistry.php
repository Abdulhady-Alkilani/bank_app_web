<?php

namespace App\Services\Scoring;

use App\Data\AiScoreResult;
use App\Enums\ModelStatus;
use App\Models\ModelRun;

class ModelRegistry
{
    private bool $resolved = false;

    private ?ModelRun $champion = null;

    /**
     * Latest run of the current champion model (memoised per instance).
     */
    public function championRun(): ?ModelRun
    {
        if (! $this->resolved) {
            $this->champion = ModelRun::query()
                ->with('model')
                ->whereHas('model', fn ($q) => $q->where('status', ModelStatus::Champion->value))
                ->orderByDesc('trained_at')
                ->orderByDesc('id')
                ->first();
            $this->resolved = true;
        }

        return $this->champion;
    }

    /**
     * Registered run of the model that actually produced the AI answer, matched on
     * model_name + model_version (the version alone when the name is missing; the champion wins ties).
     * Null when the AI did not answer (rules-only fallback) or the answering model is not registered.
     */
    public function runFor(?AiScoreResult $result): ?ModelRun
    {
        if ($result === null || blank($result->modelVersion)) {
            return null;
        }

        $champion = $this->championRun();
        if ($champion && $champion->version_number === $result->modelVersion
            && (blank($result->modelName) || $champion->model?->model_name === $result->modelName)) {
            return $champion;
        }

        return ModelRun::query()
            ->where('version_number', $result->modelVersion)
            ->when(filled($result->modelName), fn ($query) => $query->whereHas('model', fn ($q) => $q->where('model_name', $result->modelName)))
            ->orderByDesc('trained_at')
            ->orderByDesc('id')
            ->first();
    }
}
