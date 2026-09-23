<?php

namespace App\Services\StateMachines;

use App\Exceptions\StateTransitionException;

class ContractStateMachine
{
    public const STATE_DRAFT = 'draft';
    public const STATE_GENERATED = 'generated';
    public const STATE_PENDING_REVIEW = 'pending_review';
    public const STATE_APPROVED = 'approved';
    public const STATE_SENT = 'sent';
    public const STATE_SIGNED = 'signed';
    public const STATE_CANCELLED = 'cancelled';

    /**
     * Matrice des transitions autorisées pour un contrat.
     */
    protected const ALLOWED_TRANSITIONS = [
        self::STATE_DRAFT => [self::STATE_GENERATED, self::STATE_CANCELLED],
        self::STATE_GENERATED => [self::STATE_PENDING_REVIEW, self::STATE_GENERATED, self::STATE_CANCELLED],
        self::STATE_PENDING_REVIEW => [self::STATE_APPROVED, self::STATE_GENERATED, self::STATE_CANCELLED],
        self::STATE_APPROVED => [self::STATE_SENT, self::STATE_CANCELLED],
        self::STATE_SENT => [self::STATE_SIGNED, self::STATE_CANCELLED],
        self::STATE_SIGNED => [], // État terminal : contrat formellement signé et engageant
        self::STATE_CANCELLED => [], // État terminal : contrat révoqué
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
                "Transition de statut de contrat interdite : impossible de passer de '{$from}' à '{$to}'."
            );
        }
    }
}
