<?php

namespace Database\Factories;

use App\Enums\OtpStatus;
use App\Models\Customer;
use App\Models\OtpChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OtpChallenge>
 */
class OtpChallengeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'transaction_id' => null,
            'event_id' => null,
            'code_hash' => Hash::make('123456'),
            'status' => OtpStatus::Pending,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ];
    }
}
