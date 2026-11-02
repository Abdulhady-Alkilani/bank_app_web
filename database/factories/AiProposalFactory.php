<?php

namespace Database\Factories;

use App\Enums\AiProposalStatus;
use App\Models\AiProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProposal>
 */
class AiProposalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => null,
            'alert_id' => null,
            'tool_name' => 'propose_case_note',
            'payload' => ['note' => fake()->sentence()],
            'status' => AiProposalStatus::Pending,
        ];
    }
}
