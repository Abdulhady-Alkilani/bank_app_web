<?php

namespace App\Services\Ai;

use App\Contracts\AiScoringClient;
use App\Data\AiScoreResult;
use App\Exceptions\AiServiceUnavailable;

/**
 * Bound when fraud.ai.driver = "none": every call fails so the pipeline falls back to rules only.
 */
class DisabledAiScoringClient implements AiScoringClient
{
    public function score(array $features, array $context): AiScoreResult
    {
        throw new AiServiceUnavailable('AI scoring is disabled.');
    }

    public function health(): bool
    {
        return false;
    }
}
