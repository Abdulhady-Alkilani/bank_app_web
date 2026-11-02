<?php

namespace Database\Factories;

use App\Models\CaseNote;
use App\Models\InvestigationCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseNote>
 */
class CaseNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => InvestigationCase::factory(),
            'user_id' => User::factory(),
            'note' => fake()->paragraph(),
            'is_ai_generated' => false,
        ];
    }
}
