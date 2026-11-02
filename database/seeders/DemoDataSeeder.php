<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Beneficiary;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Device;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * A light data set; the simulator adds volume.
     */
    public function run(): void
    {
        $ahmed = Customer::where('email', 'ahmed@bank.test')->first();

        // Ahmed's own device, beneficiaries and a baseline of normal activity.
        if ($ahmed && $ahmed->accounts()->exists()) {
            $account = $ahmed->accounts()->first();
            $device = Device::firstOrCreate(
                ['device_fingerprint' => 'ahmed-android-device'],
                ['device_type' => 'android', 'last_ip' => '10.10.0.5', 'last_location' => 'Sanaa'],
            );
            CustomerDevice::firstOrCreate(
                ['customer_id' => $ahmed->id, 'device_id' => $device->id],
                ['is_trusted' => true, 'last_used_at' => now()],
            );

            if (! $account->beneficiaries()->exists()) {
                $beneficiaries = collect([['Family Member', 'Bank A'], ['Landlord', 'Bank B']])->map(
                    fn (array $b, int $i) => Beneficiary::factory()->create([
                        'account_id' => $account->id,
                        'beneficiary_name' => $b[0],
                        'bank_name' => $b[1],
                        'added_at' => now()->subMonths(6 + $i),
                    ])
                );

                foreach (range(1, 8) as $i) {
                    Transaction::factory()->create([
                        'account_id' => $account->id,
                        'beneficiary_id' => $beneficiaries->random()->id,
                        'device_id' => $device->id,
                        'amount' => fake()->numberBetween(50, 300),
                        'ip_address' => '10.10.0.5',
                        'transaction_time' => now()->subDays($i * 3)->setTime(fake()->numberBetween(9, 18), fake()->numberBetween(0, 59)),
                    ]);
                }
            }
        }

        // Other customers.
        if (Customer::count() >= 20) {
            return;
        }

        Customer::factory(19)->create()->each(function (Customer $customer) {
            $device = Device::factory()->create();
            CustomerDevice::factory()->create(['customer_id' => $customer->id, 'device_id' => $device->id]);

            Account::factory(fake()->numberBetween(1, 2))->create(['customer_id' => $customer->id])->each(function (Account $account) use ($device) {
                $beneficiaries = Beneficiary::factory(2)->create(['account_id' => $account->id]);

                foreach (range(1, 4) as $i) {
                    Transaction::factory()->create([
                        'account_id' => $account->id,
                        'beneficiary_id' => $beneficiaries->random()->id,
                        'device_id' => $device->id,
                        'amount' => fake()->numberBetween(30, 500),
                        'transaction_time' => now()->subDays($i * 4),
                    ]);
                }
            });
        });
    }
}
