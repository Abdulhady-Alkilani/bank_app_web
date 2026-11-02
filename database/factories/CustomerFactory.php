<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'phone' => '+9677'.fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'avg_balance' => fake()->randomFloat(2, 500, 5000),
            'usage_pattern' => ['typical_hours' => [9, 18], 'avg_txn_amount' => fake()->numberBetween(50, 500), 'cities' => ['Sanaa']],
        ];
    }
}
