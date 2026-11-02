<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'action' => 'test.action',
            'entity_type' => null,
            'entity_id' => null,
            'old_values' => [],
            'new_values' => [],
            'ip_address' => fake()->ipv4(),
        ];
    }
}
