<?php

namespace App\Data;

final readonly class RulesResult
{
    /**
     * @param  array<int, array{code: string, rule_id: int, text: string, text_en: string, weight: int, action: string, source: string}>  $triggered
     */
    public function __construct(
        public int $score,
        public array $triggered = [],
        public bool $blacklistHit = false,
        public bool $whitelistHit = false,
    ) {}
}
