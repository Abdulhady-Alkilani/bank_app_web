<?php

namespace App\Policies;

use App\Models\InvestigationCase;
use App\Models\User;

class InvestigationCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('cases.manage');
    }

    public function view(User $user, InvestigationCase $case): bool
    {
        if (! $user->hasPermission('cases.manage')) {
            return false;
        }

        return $user->hasRole(['supervisor', 'admin'])
            || $case->assigned_to === null
            || $case->assigned_to === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('cases.manage');
    }

    public function update(User $user, InvestigationCase $case): bool
    {
        return $this->view($user, $case);
    }

    public function close(User $user, InvestigationCase $case): bool
    {
        return $this->view($user, $case);
    }
}
