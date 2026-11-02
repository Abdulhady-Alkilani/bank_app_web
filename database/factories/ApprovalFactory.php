<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Models\Account;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Approval>
 */
class ApprovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ApprovalType::FreezeAccount,
            'subject_type' => Account::class,
            'subject_id' => Account::factory(),
            'requested_by' => User::factory()->supervisor(),
            'approved_by' => null,
            'status' => ApprovalStatus::Pending,
            'reason' => fake()->sentence(),
        ];
    }
}
