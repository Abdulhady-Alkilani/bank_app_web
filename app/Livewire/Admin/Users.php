<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Users extends Component
{
    use AuthorizesPermission, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editId = null;

    public string $name = '';

    public string $email = '';

    /** @var array<int, int|string> */
    public array $roleIds = [];

    public ?string $flash = null;

    public ?string $errorMessage = null;

    /** Temporary password, shown exactly once after create / reset. */
    public ?string $tempPassword = null;

    public ?string $tempFor = null;

    public function mount(): void
    {
        $this->authorizePermission('users.manage');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizePermission('users.manage');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('users.manage');
        $user = User::with('roles')->findOrFail($id);
        $this->resetForm();
        $this->editId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->roleIds = $user->roles->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorizePermission('users.manage');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editId)],
            'roleIds' => ['array'],
            'roleIds.*' => ['exists:roles,id'],
        ]);

        $roleIds = collect($data['roleIds'])->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($this->editId) {
            $user = User::with('roles')->findOrFail($this->editId);

            if ($user->id === auth()->id()) {
                $adminRole = Role::where('slug', 'admin')->value('id');
                if ($adminRole && $user->roles->contains('id', $adminRole) && ! in_array($adminRole, $roleIds, true)) {
                    $this->addError('roleIds', __('admin.users.cannot_remove_own_admin'));

                    return;
                }
            }

            $old = ['name' => $user->name, 'email' => $user->email, 'roles' => $user->roles->pluck('slug')->sort()->values()->all()];
            $user->update(['name' => $data['name'], 'email' => $data['email']]);
            $user->roles()->sync($roleIds);
            $user->unsetRelation('roles');

            $audit->log('user.updated', $user, $old, [
                'name' => $user->name, 'email' => $user->email,
                'roles' => Role::whereIn('id', $roleIds)->pluck('slug')->sort()->values()->all(),
            ]);
            $this->flash = __('admin.users.updated');
        } else {
            $password = Str::password(12, symbols: false);
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->roles()->sync($roleIds);

            $audit->log('user.created', $user, [], [
                'name' => $user->name, 'email' => $user->email,
                'roles' => Role::whereIn('id', $roleIds)->pluck('slug')->sort()->values()->all(),
            ]);

            $this->tempPassword = $password;
            $this->tempFor = $user->email;
            $this->flash = __('admin.users.created');
        }

        $this->resetForm();
    }

    public function toggleActive(int $id, AuditLogger $audit): void
    {
        $this->authorizePermission('users.manage');
        $this->resetMessages();

        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            $this->errorMessage = __('admin.users.cannot_deactivate_self');

            return;
        }

        $active = ! $user->is_active;
        $user->update(['is_active' => $active]);

        if (! $active) {
            $user->tokens()->delete();
        }

        $audit->log($active ? 'user.activated' : 'user.deactivated', $user, ['is_active' => ! $active], ['is_active' => $active]);
        $this->flash = $active ? __('admin.users.activated') : __('admin.users.deactivated');
    }

    public function resetPassword(int $id, AuditLogger $audit): void
    {
        $this->authorizePermission('users.manage');
        $this->resetMessages();

        $user = User::findOrFail($id);
        $password = Str::password(12, symbols: false);
        $user->forceFill(['password' => $password, 'remember_token' => Str::random(10)])->save();

        $audit->log('user.password_reset', $user, [], ['reset' => true]);

        $this->tempPassword = $password;
        $this->tempFor = $user->email;
        $this->flash = __('admin.users.password_reset');
    }

    public function dismissPassword(): void
    {
        $this->tempPassword = null;
        $this->tempFor = null;
    }

    private function resetMessages(): void
    {
        $this->flash = null;
        $this->errorMessage = null;
        $this->tempPassword = null;
        $this->tempFor = null;
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editId', 'name', 'email', 'roleIds']);
        $this->resetValidation();
    }

    public function render(): View
    {
        $users = User::query()->with('roles')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.users', [
            'users' => $users,
            'roles' => Role::orderBy('id')->get(),
        ])->title(__('admin.users.title'));
    }
}
