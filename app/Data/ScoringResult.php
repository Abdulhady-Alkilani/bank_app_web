<?php

namespace App\Data;

use App\Enums\Decision;
use App\Models\AccountEvent;
use App\Models\RiskScore;
use App\Models\Transaction;

final readonly class ScoringResult
{
    /**
     * @param  array<int, mixed>  $reasons
     */
    public function __construct(
        public RiskScore $riskScore,
        public int $score,
        public Decision $decision,
        public array $reasons,
        public ?int $otpChallengeId,
        public Transaction|AccountEvent $subject,
    ) {}
}
