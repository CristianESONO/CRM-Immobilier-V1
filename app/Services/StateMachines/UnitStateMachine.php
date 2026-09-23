<?php

namespace App\Services\StateMachines;

use App\Exceptions\StateTransitionException;

class UnitStateMachine
{
    public const STATE_AVAILABLE = 'available';
    public const STATE_RESERVED = 'reserved';
    public const STATE_SOLD = 'sold';

    /**
     * Matrice des transitions d'états autorisées pour un lot immobilier.
     */
    protected const ALLOWED_TRANSITIONS = [
        self::STATE_AVAILABLE => [self::STATE_RESERVED],
        self::STATE_RESERVED => [self::STATE_AVAILABLE, self::STATE_SOLD],
        self::STATE_SOLD => [], // État terminal : lot acte notarié / vente achevée
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
                "Transition de statut de lot interdite : impossible de faire passer le lot de '{$from}' à '{$to}'."
            );
        }
    }
}
