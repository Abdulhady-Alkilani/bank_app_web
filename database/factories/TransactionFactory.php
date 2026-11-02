<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'beneficiary_id' => null,
            'device_id' => null,
            'amount' => fake()->randomFloat(2, 10, 1000),
            'channel' => 'mobile',
            'ip_address' => fake()->ipv4(),
            'city' => 'Sanaa',
            'status' => TransactionStatus::Completed,
            'transaction_time' => now(),
            'idempotency_key' => null,
        ];
    }

    public function held(): static
    {
        return $this->state(fn () => ['status' => TransactionStatus::Held]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['status' => TransactionStatus::Blocked]);
    }
}
