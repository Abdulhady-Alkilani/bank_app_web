<?php

namespace Database\Factories;

use App\Enums\Decision;
use App\Models\RiskScore;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiskScore>
 */
class RiskScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'event_id' => null,
            'model_run_id' => null,
            'score' => 20,
            'decision' => Decision::Allow,
            'reasons' => [],
            'evaluated_at' => now(),
            'rule_score' => 0,
            'model_probability' => 0.1,
            'used_fallback' => false,
            'latency_ms' => fake()->numberBetween(20, 200),
            'features' => [],
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['score' => 92, 'decision' => Decision::Block, 'rule_score' => 90, 'model_probability' => 0.9]);
    }

    public function challenged(): static
    {
        return $this->state(fn () => ['score' => 60, 'decision' => Decision::Challenge, 'rule_score' => 55, 'model_probability' => 0.6]);
    }
}
