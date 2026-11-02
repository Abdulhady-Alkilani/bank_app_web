<?php

namespace Database\Factories;

use App\Enums\ModelStatus;
use App\Models\MlModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MlModel>
 */
class MlModelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_name' => fake()->unique()->slug(2),
            'algorithm' => 'logistic_regression',
            'status' => ModelStatus::Candidate,
        ];
    }

    public function champion(): static
    {
        return $this->state(fn () => ['status' => ModelStatus::Champion]);
    }
}
