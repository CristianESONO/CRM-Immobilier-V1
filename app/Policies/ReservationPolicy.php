<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Reservation $reservation): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        // Cloisonnement strict multi-tenant
        return $reservation->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        if (!$user->is_active || $user->isObserver()) {
            return false;
        }

        return true;
    }

    public function update(User $user, Reservation $reservation): bool
    {
        if (!$user->is_active || $user->isObserver()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($reservation->tenant_id !== $user->tenant_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        // Commercial : autorisé si assigné au dossier ou non assigné
        return empty($reservation->assigned_to) || $reservation->assigned_to === $user->id;
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        // Seuls les administrateurs du même tenant peuvent supprimer un enregistrement
        return $user->isAdmin() && $reservation->tenant_id === $user->tenant_id;
    }

    public function recordPayment(User $user, Reservation $reservation): bool
    {
        if (!$user->is_active || $user->isObserver()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $reservation->tenant_id === $user->tenant_id;
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        if (!$user->is_active || $user->isObserver()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($reservation->tenant_id !== $user->tenant_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return empty($reservation->assigned_to) || $reservation->assigned_to === $user->id;
    }
}
