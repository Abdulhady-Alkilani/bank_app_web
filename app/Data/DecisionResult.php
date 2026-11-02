<?php

namespace App\Data;

use App\Enums\Decision;

final readonly class DecisionResult
{
    /**
     * @param  array<int, array{code: string, text: string, weight: float|int, source: string}>  $reasons
     */
    public function __construct(
        public int $score,
        public Decision $decision,
        public int $ruleScore,
        public ?float $modelProbability,
        public bool $usedFallback,
        public array $reasons,
    ) {}
}
