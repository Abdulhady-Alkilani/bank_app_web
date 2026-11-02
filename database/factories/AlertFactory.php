<?php

namespace Database\Factories;

use App\Enums\AlertStatus;
use App\Enums\Priority;
use App\Models\Alert;
use App\Models\RiskScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => null,
            'risk_score_id' => RiskScore::factory(),
            'status' => AlertStatus::Open,
            'priority' => Priority::Medium,
            'assigned_to' => null,
            'decision' => null,
        ];
    }
}
