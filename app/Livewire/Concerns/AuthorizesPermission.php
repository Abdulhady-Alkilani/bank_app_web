<?php

namespace App\Livewire\Concerns;

trait AuthorizesPermission
{
    protected function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->hasPermission($permission), 403, __('common.forbidden'));
    }
}
