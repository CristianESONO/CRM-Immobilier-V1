<?php

namespace App\Listeners;

use App\Events\ContractSigned;
use App\Events\PaymentRecorded;

class ReservationStatusSubscriber
{
    /**
     * Lors de la signature contractuelle, confirme automatiquement la réservation.
     */
    public function handleContractSigned(ContractSigned $event): void
    {
        $reservation = $event->contract->reservation;
        if ($reservation && $reservation->status === 'option') {
            $reservation->update(['status' => 'confirmed']);
        }
    }

    /**
     * Après encaissement d'un paiement, vérifie si le dossier est intégralement soldé.
     */
    public function handlePaymentRecorded(PaymentRecorded $event): void
    {
        $reservation = $event->payment->reservation;
        if ($reservation && $reservation->remaining_balance <= 0 && $reservation->status === 'confirmed') {
            $reservation->update(['status' => 'completed']);
            if ($reservation->unit && $reservation->unit->status !== 'sold') {
                $reservation->unit->update(['status' => 'sold']);
            }
        }
    }

    public function subscribe($events): array
    {
        return [
            ContractSigned::class => 'handleContractSigned',
            PaymentRecorded::class => 'handlePaymentRecorded',
        ];
    }
}
