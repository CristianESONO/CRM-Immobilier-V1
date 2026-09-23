<?php

namespace App\Policies;

use App\Models\BuyerDocument;
use App\Models\User;

class BuyerDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, BuyerDocument $doc): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $doc->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->is_active && !$user->isObserver();
    }

    public function update(User $user, BuyerDocument $doc): bool
    {
        if (!$user->is_active || $user->isObserver()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $doc->tenant_id === $user->tenant_id;
    }

    public function verify(User $user, BuyerDocument $doc): bool
    {
        if (!$user->is_active || !$user->isAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $doc->tenant_id === $user->tenant_id;
    }

    public function reject(User $user, BuyerDocument $doc): bool
    {
        if (!$user->is_active || !$user->isAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $doc->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, BuyerDocument $doc): bool
    {
        if (!$user->is_active || !$user->isAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $doc->tenant_id === $user->tenant_id;
    }
}
