<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_fingerprint' => fake()->unique()->sha1(),
            'device_type' => fake()->randomElement(['android', 'ios', 'web']),
            'sim_changed_at' => null,
            'last_ip' => fake()->ipv4(),
            'last_location' => 'Sanaa',
        ];
    }
}
