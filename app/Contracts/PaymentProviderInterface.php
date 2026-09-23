<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Models\Reservation;

interface PaymentProviderInterface
{
    /**
     * Initie un paiement électronique ou virement (Stripe, Mobile Money, Gateway Banque).
     *
     * @param Reservation $reservation
     * @param float $amount
     * @param string $currency
     * @return array  Ex: ['transaction_id' => '...', 'checkout_url' => '...']
     */
    public function initiatePayment(Reservation $reservation, float $amount, string $currency = 'EUR'): array;

    /**
     * Vérifie le statut d'un paiement externe.
     *
     * @param string $transactionId
     * @return string  'pending' | 'completed' | 'failed'
     */
    public function verifyStatus(string $transactionId): string;

    /**
     * Enregistre le paiement confirmé et met à jour le statut interne.
     *
     * @param string $transactionId
     * @return Payment|null
     */
    public function confirmPayment(string $transactionId): ?Payment;
}
