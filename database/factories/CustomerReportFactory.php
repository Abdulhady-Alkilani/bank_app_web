<?php

namespace Database\Factories;

use App\Enums\CustomerReportStatus;
use App\Models\Customer;
use App\Models\CustomerReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerReport>
 */
class CustomerReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'transaction_id' => null,
            'description' => fake()->paragraph(),
            'status' => CustomerReportStatus::New,
        ];
    }
}
