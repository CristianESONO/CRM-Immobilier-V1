<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Contract $contract): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $contract->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        if (!$user->is_active || $user->isObserver()) {
            return false;
        }

        return true;
    }

    public function update(User $user, Contract $contract): bool
    {
        if (!$user->is_active || $user->isObserver()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $contract->tenant_id === $user->tenant_id;
    }

    public function approve(User $user, Contract $contract): bool
    {
        if (!$user->is_active || !$user->isAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $contract->tenant_id === $user->tenant_id;
    }

    public function sign(User $user, Contract $contract): bool
    {
        if (!$user->is_active || !$user->isAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $contract->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Contract $contract): bool
    {
        if (!$user->is_active || !$user->isAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $contract->tenant_id === $user->tenant_id;
    }
}
