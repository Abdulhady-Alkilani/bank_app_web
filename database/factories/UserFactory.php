<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use App\Support\RbacMatrix;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withRole(string $slug): static
    {
        return $this->afterCreating(function (User $user) use ($slug) {
            $role = RbacMatrix::ensureRole($slug);
            $user->roles()->syncWithoutDetaching([$role->getKey()]);
            $user->unsetRelation('roles');
        });
    }

    public function admin(): static
    {
        return $this->withRole('admin');
    }

    public function supervisor(): static
    {
        return $this->withRole('supervisor');
    }

    public function analyst(): static
    {
        return $this->withRole('analyst');
    }

    public function customer(): static
    {
        return $this->withRole('customer')->afterCreating(function (User $user) {
            Customer::factory()->create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
        });
    }
}
