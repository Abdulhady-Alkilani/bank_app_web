<?php

namespace App\Jobs;

use App\Models\RiskScore;
use App\Services\Alerts\AlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateAlertForRiskScore implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $riskScoreId) {}

    public function handle(AlertService $alerts): void
    {
        $riskScore = RiskScore::find($this->riskScoreId);

        if ($riskScore) {
            $alerts->createForRiskScore($riskScore);
        }
    }
}
