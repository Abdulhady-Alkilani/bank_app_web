<?php

use App\Livewire\Admin\Roles;
use App\Livewire\Admin\Users;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    foreach (['supervisor', 'analyst', 'customer'] as $slug) {
        User::factory()->withRole($slug)->create();
    }
});

it('creates a user with a one-time temporary password and audits it', function () {
    $analystRole = Role::where('slug', 'analyst')->first();

    $component = Livewire::actingAs($this->admin)->test(Users::class)
        ->call('create')
        ->set('name', 'Sara Ali')
        ->set('email', 'sara@example.test')
        ->set('roleIds', [(string) $analystRole->id])
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'sara@example.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('analyst'))->toBeTrue()
        ->and($user->is_active)->toBeTrue();

    $temp = $component->get('tempPassword');
    expect($temp)->toBeString()->not->toBeEmpty()
        ->and(Hash::check($temp, $user->password))->toBeTrue();

    $component->call('dismissPassword')->assertSet('tempPassword', null);

    $log = AuditLog::where('action', 'user.created')->first();
    expect($log)->not->toBeNull()
        ->and(json_encode($log->new_values))->not->toContain($temp);
});

it('edits roles of an existing user', function () {
    $user = User::factory()->analyst()->create();
    $supervisorRole = Role::where('slug', 'supervisor')->first();

    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('edit', $user->id)
        ->set('name', 'Renamed')
        ->set('roleIds', [(string) $supervisorRole->id])
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->name)->toBe('Renamed')
        ->and($user->hasRole('supervisor'))->toBeTrue()
        ->and($user->hasRole('analyst'))->toBeFalse();
    expect(AuditLog::where('action', 'user.updated')->count())->toBe(1);
});

it('deactivates and reactivates other users but never the current one', function () {
    $user = User::factory()->analyst()->create();

    $component = Livewire::actingAs($this->admin)->test(Users::class)
        ->call('toggleActive', $user->id);
    expect($user->fresh()->is_active)->toBeFalse();

    $component->call('toggleActive', $user->id);
    expect($user->fresh()->is_active)->toBeTrue();

    $component->call('toggleActive', $this->admin->id)
        ->assertSet('errorMessage', __('admin.users.cannot_deactivate_self'));
    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('resets a password and shows the temporary one', function () {
    $user = User::factory()->analyst()->create();

    $component = Livewire::actingAs($this->admin)->test(Users::class)->call('resetPassword', $user->id);

    expect(Hash::check($component->get('tempPassword'), $user->fresh()->password))->toBeTrue();
    expect(AuditLog::where('action', 'user.password_reset')->exists())->toBeTrue();
});

it('updates the permission matrix and audits the change', function () {
    $analyst = Role::where('slug', 'analyst')->first();
    expect($analyst->permissions->pluck('name'))->not->toContain('reports.view');

    $grid = Livewire::actingAs($this->admin)->test(Roles::class)->get('grid');
    $grid[$analyst->id][] = 'reports.view';

    Livewire::actingAs($this->admin)->test(Roles::class)
        ->set('grid', $grid)
        ->call('save')
        ->assertHasNoErrors();

    expect($analyst->fresh()->permissions->pluck('name'))->toContain('reports.view');
    $log = AuditLog::where('action', 'role.permissions_updated')->first();
    expect($log->new_values['added'])->toBe(['reports.view']);
});

it('has no training permission and exposes models.view', function () {
    expect(Permission::where('name', 'models.view')->exists())->toBeTrue()
        ->and(Permission::where('name', 'like', '%train%')->exists())->toBeFalse();
});

it('refuses to remove roles.manage from the admin role', function () {
    $adminRole = Role::where('slug', 'admin')->first();
    $grid = Livewire::actingAs($this->admin)->test(Roles::class)->get('grid');
    $grid[$adminRole->id] = array_values(array_diff($grid[$adminRole->id], ['roles.manage']));

    Livewire::actingAs($this->admin)->test(Roles::class)
        ->set('grid', $grid)
        ->call('save')
        ->assertSet('errorMessage', __('admin.roles.admin_lockout'));

    expect($adminRole->fresh()->permissions->pluck('name'))->toContain('roles.manage');
});
