<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Account;
use App\Models\AccountEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountEvent>
 */
class AccountEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'device_id' => null,
            'event_type' => EventType::Login,
            'ip_address' => fake()->ipv4(),
            'details' => [],
            'event_time' => now(),
            'idempotency_key' => null,
        ];
    }
}
