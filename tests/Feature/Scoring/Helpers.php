<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Device;
use Database\Seeders\MlModelSeeder;
use Database\Seeders\RuleSeeder;
use Database\Seeders\SettingsSeeder;

/**
 * Seeds the rules, default settings and the champion model run.
 */
function scoringSeed(): void
{
    test()->seed([RuleSeeder::class, SettingsSeeder::class, MlModelSeeder::class]);
}

/**
 * A customer with one account, one trusted baseline device in Sanaa and an average balance of 1000.
 *
 * @return array{customer: Customer, account: Account, device: Device}
 */
function scoringCustomer(array $account = []): array
{
    $customer = Customer::factory()->create([
        'avg_balance' => 1000,
        'usage_pattern' => ['cities' => ['Sanaa']],
    ]);
    $acct = Account::factory()->create(['customer_id' => $customer->id, 'transfer_limit' => 20000] + $account);
    $device = Device::factory()->create(['last_location' => 'Sanaa']);
    CustomerDevice::create(['customer_id' => $customer->id, 'device_id' => $device->id, 'is_trusted' => true, 'last_used_at' => now()]);

    return ['customer' => $customer, 'account' => $acct, 'device' => $device];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function scoringTransfer(Account $account, Device $device, float $amount, string $beneficiary = '9000000001', array $overrides = []): array
{
    return $overrides + [
        'event_type' => 'transfer',
        'account_number' => $account->account_number,
        'amount' => $amount,
        'beneficiary_account' => $beneficiary,
        'device_fingerprint' => $device->device_fingerprint,
        'city' => 'Sanaa',
        'ip_address' => '10.0.0.5',
    ];
}
