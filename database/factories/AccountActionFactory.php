<?php

namespace Database\Factories;

use App\Enums\AccountActionStatus;
use App\Enums\AccountActionType;
use App\Models\Account;
use App\Models\AccountAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountAction>
 */
class AccountActionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'action' => AccountActionType::FreezeAccount,
            'status' => AccountActionStatus::Executed,
            'reason' => fake()->sentence(),
            'executed_at' => now(),
        ];
    }
}
