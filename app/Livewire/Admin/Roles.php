<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Roles extends Component
{
    use AuthorizesPermission;

    /** @var array<int, array<int, string>> role id => granted permission names */
    public array $grid = [];

    public ?string $flash = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->authorizePermission('roles.manage');
        $this->load();
    }

    private function load(): void
    {
        $this->grid = Role::with('permissions')->get()
            ->mapWithKeys(fn (Role $role) => [$role->id => $role->permissions->pluck('name')->values()->all()])
            ->all();
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorizePermission('roles.manage');
        $this->flash = $this->errorMessage = null;

        $permissions = Permission::pluck('id', 'name');
        $roles = Role::with('permissions')->get();

        // Never let the admin role lose the ability to manage roles (lock-out guard).
        $admin = $roles->firstWhere('slug', 'admin');
        if ($admin && ! in_array('roles.manage', $this->grid[$admin->id] ?? [], true)) {
            $this->errorMessage = __('admin.roles.admin_lockout');

            return;
        }

        $changed = 0;
        foreach ($roles as $role) {
            $new = collect($this->grid[$role->id] ?? [])->filter(fn ($name) => $permissions->has($name))->unique()->sort()->values();
            $old = $role->permissions->pluck('name')->sort()->values();

            if ($new->all() === $old->all()) {
                continue;
            }

            $role->permissions()->sync($new->map(fn ($name) => $permissions[$name])->all());
            $audit->log('role.permissions_updated', $role, ['permissions' => $old->all()], [
                'permissions' => $new->all(),
                'added' => $new->diff($old)->values()->all(),
                'removed' => $old->diff($new)->values()->all(),
            ]);
            $changed++;
        }

        $this->flash = $changed ? __('admin.roles.saved') : __('admin.roles.no_changes');
    }

    public function render(): View
    {
        return view('livewire.admin.roles', [
            'roles' => Role::orderBy('id')->get(),
            'permissions' => Permission::orderBy('name')->get(),
        ])->title(__('admin.roles.title'));
    }
}
