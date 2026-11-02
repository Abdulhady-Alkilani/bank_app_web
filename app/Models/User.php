<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'is_active', 'email_verified_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * @param  string|array<int, string>  $roles  role slug(s); true when the user has any of them
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return $this->roleSlugs()->intersect($roles)->isNotEmpty();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissionNames()->contains($permission);
    }

    public function assignRole(string|Role $role): static
    {
        $role = $role instanceof Role ? $role : Role::where('slug', $role)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$role->getKey()]);
        $this->unsetRelation('roles');

        return $this;
    }

    public function isStaff(): bool
    {
        return $this->hasRole(['admin', 'supervisor', 'analyst']);
    }

    /**
     * Home path according to the user's highest role.
     */
    public function homePath(): string
    {
        return match (true) {
            $this->hasRole('admin') => '/admin',
            $this->hasRole('supervisor') => '/supervisor',
            $this->hasRole('analyst') => '/analyst',
            $this->hasRole('customer') => '/portal',
            default => '/profile/security',
        };
    }

    /**
     * @return Collection<int, string>
     */
    public function roleSlugs()
    {
        return $this->loadMissing('roles')->roles->pluck('slug');
    }

    /**
     * @return Collection<int, string>
     */
    public function permissionNames()
    {
        return $this->loadMissing('roles.permissions')->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();
    }
}
