<?php

use App\Enums\ModelStatus;
use App\Models\Account;
use App\Models\MlModel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Rule;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\DatabaseSeeder;

it('runs every seeder and creates the baseline data', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Role::count())->toBe(4)
        ->and(Permission::count())->toBe(25)
        ->and(Rule::where('is_active', true)->count())->toBeGreaterThanOrEqual(8)
        ->and(MlModel::where('status', ModelStatus::Champion)->first()?->runs)->toHaveCount(1)
        ->and(app(SettingsService::class)->get('decision.allow_max'))->toBe(39)
        ->and(app(SettingsService::class)->get('security.require_staff_2fa'))->toBeFalse();

    $ahmed = User::where('email', 'ahmed@bank.test')->first();
    expect($ahmed->hasRole('customer'))->toBeTrue()
        ->and($ahmed->customer->accounts)->toHaveCount(1)
        ->and(Account::count())->toBeGreaterThan(10);

    foreach (['admin', 'supervisor', 'analyst', 'analyst2'] as $name) {
        expect(User::where('email', "{$name}@bank.test")->exists())->toBeTrue();
    }
});

it('is idempotent', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Role::count())->toBe(4)->and(User::count())->toBe(5);
});

it('has a working factory for every model', function () {
    foreach (glob(app_path('Models/*.php')) as $file) {
        $class = 'App\\Models\\'.basename($file, '.php');
        if (in_array($class, [User::class, Role::class], true) || ! method_exists($class, 'factory')) {
            continue;
        }

        expect($class::factory()->create())->toBeInstanceOf($class);
    }
});
