<?php

namespace Database\Factories;

use App\Enums\RuleSuggestionStatus;
use App\Models\RuleSuggestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuleSuggestion>
 */
class RuleSuggestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'suggested_by' => User::factory()->analyst(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'conditions' => ['all' => [['feature' => 'txn_count_1h', 'op' => '>=', 'value' => 5]]],
            'status' => RuleSuggestionStatus::Pending,
        ];
    }
}
