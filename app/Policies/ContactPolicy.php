<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Contact $contact): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $contact->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        if (!$user->is_active || $user->isObserver()) {
            return false;
        }

        return true;
    }

    public function update(User $user, Contact $contact): bool
    {
        if (!$user->is_active || $user->isObserver()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($contact->tenant_id !== $user->tenant_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return empty($contact->assigned_to) || $contact->assigned_to === $user->id;
    }

    public function delete(User $user, Contact $contact): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $contact->tenant_id === $user->tenant_id;
    }
}
