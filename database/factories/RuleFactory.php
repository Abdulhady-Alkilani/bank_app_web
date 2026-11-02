<?php

namespace Database\Factories;

use App\Models\Rule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rule>
 */
class RuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'weight' => 20,
            'priority' => 100,
            'is_active' => true,
            'conditions' => ['event_types' => ['transfer'], 'all' => [['feature' => 'amount', 'op' => '>=', 'value' => 1000]]],
            'action' => 'flag',
        ];
    }
}
