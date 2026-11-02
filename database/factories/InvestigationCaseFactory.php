<?php

namespace Database\Factories;

use App\Enums\CaseStatus;
use App\Enums\Priority;
use App\Models\InvestigationCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestigationCase>
 */
class InvestigationCaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'assigned_to' => null,
            'status' => CaseStatus::Open,
            'priority' => Priority::Medium,
        ];
    }
}
