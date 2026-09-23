<?php

namespace App\Services\StateMachines;

use App\Exceptions\StateTransitionException;

class ReservationStateMachine
{
    public const STATE_OPTION = 'option';
    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_COMPLETED = 'completed';
    public const STATE_CANCELLED = 'cancelled';

    /**
     * Matrice des transitions d'états autorisées pour une réservation.
     */
    protected const ALLOWED_TRANSITIONS = [
        self::STATE_OPTION => [self::STATE_CONFIRMED, self::STATE_COMPLETED, self::STATE_CANCELLED],
        self::STATE_CONFIRMED => [self::STATE_COMPLETED, self::STATE_CANCELLED],
        self::STATE_COMPLETED => [], // État terminal : lot vendu, dossier clos
        self::STATE_CANCELLED => [], // État terminal : option/réservation rompue
    ];

    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    public static function validateTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new StateTransitionException(
                "Transition de statut de réservation interdite : impossible de passer de '{$from}' à '{$to}'."
            );
        }
    }
}
