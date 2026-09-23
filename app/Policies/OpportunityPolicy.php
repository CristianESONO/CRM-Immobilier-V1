<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $opportunity->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        if (!$user->is_active || $user->isObserver()) {
            return false;
        }

        return true;
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        if (!$user->is_active || $user->isObserver()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($opportunity->tenant_id !== $user->tenant_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return empty($opportunity->assigned_to) || $opportunity->assigned_to === $user->id;
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $opportunity->tenant_id === $user->tenant_id;
    }
}
