<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Beneficiary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beneficiary>
 */
class BeneficiaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'beneficiary_account' => fake()->numerify('##########'),
            'beneficiary_name' => fake()->name(),
            'bank_name' => fake()->randomElement(['Bank A', 'Bank B', 'Bank C']),
            'added_at' => now()->subDays(fake()->numberBetween(10, 300)),
            'is_frozen' => false,
        ];
    }

    public function recent(): static
    {
        return $this->state(fn () => ['added_at' => now()->subMinutes(10)]);
    }

    public function frozen(): static
    {
        return $this->state(fn () => ['is_frozen' => true]);
    }
}
