<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_name' => $name = fake()->unique()->word(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'label' => $name,
        ];
    }
}
