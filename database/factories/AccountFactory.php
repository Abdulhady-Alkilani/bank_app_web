<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'account_number' => '10'.fake()->unique()->numerify('##########'),
            'type' => 'current',
            'status' => AccountStatus::Active,
            'balance' => fake()->randomFloat(2, 1000, 100000),
            'transfer_limit' => null,
        ];
    }

    public function frozen(): static
    {
        return $this->state(fn () => ['status' => AccountStatus::Frozen]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => AccountStatus::Closed]);
    }
}
