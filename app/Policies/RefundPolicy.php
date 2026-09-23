<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\User;

class RefundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Refund $refund): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $refund->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        if (!$user->is_active || $user->isObserver()) {
            return false;
        }

        return true;
    }

    public function approve(User $user, Refund $refund): bool
    {
        if (!$user->is_active || !$user->isAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $refund->tenant_id === $user->tenant_id;
    }

    public function process(User $user, Refund $refund): bool
    {
        if (!$user->is_active || !$user->isAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $refund->tenant_id === $user->tenant_id;
    }

    public function reject(User $user, Refund $refund): bool
    {
        if (!$user->is_active || !$user->isAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $refund->tenant_id === $user->tenant_id;
    }
}
