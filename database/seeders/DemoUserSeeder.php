<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $staff = [
            ['admin@bank.test', 'مدير النظام', 'admin'],
            ['supervisor@bank.test', 'مشرف الامتثال', 'supervisor'],
            ['analyst@bank.test', 'المحلل الأول', 'analyst'],
            ['analyst2@bank.test', 'المحلل الثاني', 'analyst'],
        ];

        foreach ($staff as [$email, $name, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => self::PASSWORD, 'is_active' => true, 'email_verified_at' => now()],
            );
            $user->assignRole($role);
        }

        $ahmed = User::updateOrCreate(
            ['email' => 'ahmed@bank.test'],
            ['name' => 'أحمد', 'password' => self::PASSWORD, 'is_active' => true, 'email_verified_at' => now()],
        );
        $ahmed->assignRole('customer');

        $customer = Customer::updateOrCreate(
            ['user_id' => $ahmed->id],
            [
                'name' => 'أحمد',
                'phone' => '+967711000001',
                'email' => 'ahmed@bank.test',
                'avg_balance' => 2000,
                'usage_pattern' => ['typical_hours' => [9, 18], 'avg_txn_amount' => 150, 'cities' => ['Sanaa']],
            ],
        );

        Account::updateOrCreate(
            ['account_number' => '1000000001'],
            ['customer_id' => $customer->id, 'type' => 'current', 'status' => AccountStatus::Active, 'balance' => 25000],
        );
    }
}
