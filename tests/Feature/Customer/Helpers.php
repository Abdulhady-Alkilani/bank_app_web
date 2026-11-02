<?php

use App\Models\Account;
use App\Models\Beneficiary;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Device;
use App\Models\User;
use Database\Seeders\MlModelSeeder;
use Database\Seeders\RuleSeeder;
use Database\Seeders\SettingsSeeder;

function portalSeed(): void
{
    test()->seed([RuleSeeder::class, SettingsSeeder::class, MlModelSeeder::class]);
}

/**
 * A portal customer: user with the customer role, one account (balance 50,000), a trusted device in Sanaa,
 * an average balance of 1,000 and one saved beneficiary.
 *
 * @return array{user: User, customer: Customer, account: Account, device: Device, beneficiary: Beneficiary}
 */
function portalCustomer(string $name = 'Ahmed'): array
{
    $user = User::factory()->customer()->create(['name' => $name]);
    $customer = $user->customer;
    $customer->update(['avg_balance' => 1000, 'usage_pattern' => ['cities' => ['Sanaa']]]);

    $account = Account::factory()->create(['customer_id' => $customer->id, 'balance' => 50000, 'transfer_limit' => 20000]);
    $device = Device::factory()->create(['last_location' => 'Sanaa', 'last_ip' => '10.10.0.5', 'device_type' => 'android']);
    CustomerDevice::create(['customer_id' => $customer->id, 'device_id' => $device->id, 'is_trusted' => true, 'last_used_at' => now()]);
    $beneficiary = Beneficiary::factory()->create(['account_id' => $account->id, 'beneficiary_name' => 'Landlord', 'added_at' => now()->subMonths(3)]);

    return compact('user', 'customer', 'account', 'device', 'beneficiary');
}
