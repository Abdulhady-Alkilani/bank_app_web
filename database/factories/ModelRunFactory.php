<?php

namespace Database\Factories;

use App\Models\MlModel;
use App\Models\ModelRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModelRun>
 */
class ModelRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_id' => MlModel::factory(),
            'version_number' => 'v'.fake()->numerify('1.#.#'),
            'metrics' => ['auc' => 0.93, 'precision' => 0.81, 'recall' => 0.77, 'f1' => 0.79],
            'trained_at' => now(),
        ];
    }
}
