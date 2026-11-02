<?php

namespace App\Data;

final readonly class AiScoreResult
{
    /**
     * @param  array<int, array{name: string, value?: mixed, impact?: float|int}>  $topFeatures
     */
    public function __construct(
        public float $probability,
        public ?string $modelName = null,
        public ?string $modelVersion = null,
        public array $topFeatures = [],
        public int $latencyMs = 0,
    ) {}
}
