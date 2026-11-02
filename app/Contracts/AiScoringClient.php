<?php

namespace App\Contracts;

use App\Data\AiScoreResult;

interface AiScoringClient
{
    /**
     * @param  array<string, mixed>  $features
     * @param  array<string, mixed>  $context
     */
    public function score(array $features, array $context): AiScoreResult;

    public function health(): bool;
}
