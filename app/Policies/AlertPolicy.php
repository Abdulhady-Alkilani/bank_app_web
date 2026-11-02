<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('alerts.view');
    }

    /**
     * Analysts see alerts assigned to them or still unassigned; supervisors and admins see all.
     */
    public function view(User $user, Alert $alert): bool
    {
        if (! $user->hasPermission('alerts.view')) {
            return false;
        }

        return $user->hasRole(['supervisor', 'admin'])
            || $alert->assigned_to === null
            || $alert->assigned_to === $user->getKey();
    }

    public function decide(User $user, Alert $alert): bool
    {
        return $user->hasPermission('alerts.decide') && $this->view($user, $alert);
    }

    public function act(User $user, Alert $alert): bool
    {
        return $this->decide($user, $alert);
    }
}
