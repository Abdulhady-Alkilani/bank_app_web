<?php

namespace Database\Factories;

use App\Enums\LabelSource;
use App\Models\Label;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'is_fraud' => fake()->boolean(),
            'source' => LabelSource::Analyst,
            'labeled_by' => null,
        ];
    }
}
