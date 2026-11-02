<?php

use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:admin,supervisor'])->get('/_test/role', fn () => 'ok');
    Route::middleware(['web', 'auth', 'permission:approvals.approve'])->get('/_test/permission', fn () => 'ok');
});

it('checks roles and permissions', function () {
    $analyst = User::factory()->analyst()->create();

    expect($analyst->hasRole('analyst'))->toBeTrue()
        ->and($analyst->hasRole(['admin', 'analyst']))->toBeTrue()
        ->and($analyst->hasRole('admin'))->toBeFalse()
        ->and($analyst->hasPermission('alerts.view'))->toBeTrue()
        ->and($analyst->hasPermission('approvals.approve'))->toBeFalse();
});

it('assigns roles', function () {
    $user = User::factory()->create();
    expect($user->hasRole('supervisor'))->toBeFalse();

    User::factory()->supervisor()->create(); // makes sure the role exists
    $user->assignRole('supervisor');

    expect($user->hasRole('supervisor'))->toBeTrue()
        ->and($user->hasPermission('approvals.approve'))->toBeTrue();
});

it('maps abilities to permission names through Gate::before', function () {
    $supervisor = User::factory()->supervisor()->create();
    $analyst = User::factory()->analyst()->create();

    expect(Gate::forUser($supervisor)->allows('approvals.approve'))->toBeTrue()
        ->and(Gate::forUser($analyst)->allows('approvals.approve'))->toBeFalse()
        ->and(Gate::forUser($analyst)->allows('alerts.decide'))->toBeTrue();
});

it('guards routes with the role middleware', function () {
    $this->get('/_test/role')->assertRedirect(route('login'));

    $this->actingAs(User::factory()->analyst()->create())->get('/_test/role')->assertForbidden();
    $this->actingAs(User::factory()->supervisor()->create())->get('/_test/role')->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get('/_test/role')->assertOk();
});

it('guards routes with the permission middleware', function () {
    $this->actingAs(User::factory()->analyst()->create())->get('/_test/permission')->assertForbidden();
    $this->actingAs(User::factory()->supervisor()->create())->get('/_test/permission')->assertOk();
});

it('restricts the role areas', function () {
    $customer = User::factory()->customer()->create();
    $analyst = User::factory()->analyst()->create();

    $this->actingAs($customer)->get('/admin')->assertForbidden();
    $this->actingAs($customer)->get('/analyst')->assertForbidden();
    $this->actingAs($analyst)->get('/admin')->assertForbidden();
    $this->actingAs($analyst)->get('/supervisor')->assertForbidden();
    $this->actingAs($analyst)->get('/analyst')->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get('/supervisor')->assertOk();
    $this->actingAs($customer)->get('/portal')->assertOk();
});

it('forces staff to enable 2FA only when the setting is on', function () {
    $analyst = User::factory()->analyst()->create();

    $this->actingAs($analyst)->get('/analyst')->assertOk();

    app(SettingsService::class)->set('security.require_staff_2fa', true);

    $this->actingAs($analyst)->get('/analyst')->assertRedirect(route('profile.security'));
});
