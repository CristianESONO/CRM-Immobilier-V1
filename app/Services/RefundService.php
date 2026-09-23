<?php

namespace App\Services;

use App\Exceptions\FinancialException;
use App\Models\Refund;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RefundService
{
    /**
     * Initialise une demande formelle de remboursement suite à désistement ou annulation.
     */
    public function requestRefund(
        Reservation $reservation,
        float $amount,
        string $reason,
        ?int $userId = null
    ): Refund {
        return DB::transaction(function () use ($reservation, $amount, $reason, $userId) {
            if ($amount <= 0) {
                throw new FinancialException("Le montant du remboursement doit être strictement supérieur à zéro.");
            }

            $totalPaid = (float) $reservation->payments()->where('status', 'validated')->sum('amount');
            if ($amount > $totalPaid) {
                throw new FinancialException(sprintf(
                    "Le montant du remboursement (%s FCFA) ne peut pas excéder la somme effectivement encaissée (%s FCFA).",
                    number_format($amount, 0, ',', ' '),
                    number_format($totalPaid, 0, ',', ' ')
                ));
            }

            $tenantId = $reservation->tenant_id;
            $count = Refund::where('tenant_id', $tenantId)->count() + 1;
            $reference = sprintf('REF-%s-%03d', now()->format('Y'), $count);

            $refund = Refund::create([
                'tenant_id' => $tenantId,
                'reservation_id' => $reservation->id,
                'reference' => $reference,
                'amount' => $amount,
                'reason' => $reason,
                'status' => 'pending',
                'requested_by_user_id' => $userId ?? Auth::id(),
                'requested_at' => now(),
            ]);

            AuditService::log('REFUND_REQUESTED', $refund, [
                'amount' => $amount,
                'reservation_reference' => $reservation->reference,
                'reason' => $reason,
            ], null, $userId, $tenantId);

            return $refund;
        });
    }

    /**
     * Valide et approuve la demande de remboursement (Réservé à la Direction / Finance).
     */
    public function approveRefund(Refund $refund, ?int $userId = null): Refund
    {
        return DB::transaction(function () use ($refund, $userId) {
            if ($refund->status !== 'pending') {
                throw new FinancialException("Seule une demande au statut 'pending' peut être approuvée.");
            }

            $oldValues = $refund->toArray();

            $refund->update([
                'status' => 'approved',
                'approved_by_user_id' => $userId ?? Auth::id(),
                'approved_at' => now(),
            ]);

            AuditService::log('REFUND_APPROVED', $refund, $refund->fresh()->toArray(), $oldValues, $userId);

            return $refund->fresh();
        });
    }

    /**
     * Enregistre l'exécution effective du versement bancaire de remboursement.
     */
    public function processRefund(Refund $refund, array $paymentData, ?int $userId = null): Refund
    {
        return DB::transaction(function () use ($refund, $paymentData, $userId) {
            if (!in_array($refund->status, ['approved', 'processing'], true)) {
                throw new FinancialException("Le remboursement doit être préalablement approuvé avant d'être exécuté.");
            }

            $oldValues = $refund->toArray();

            $refund->update([
                'status' => 'completed',
                'processed_by_user_id' => $userId ?? Auth::id(),
                'processed_at' => now(),
                'payment_method' => $paymentData['payment_method'] ?? 'bank_transfer',
                'proof_reference' => $paymentData['proof_reference'] ?? null,
                'notes' => $paymentData['notes'] ?? null,
            ]);

            AuditService::log('REFUND_COMPLETED', $refund, $refund->fresh()->toArray(), $oldValues, $userId);

            \App\Events\RefundCompleted::dispatch($refund->fresh(), Auth::user());

            return $refund->fresh();
        });
    }

    /**
     * Rejette la demande de remboursement avec justification formelle.
     */
    public function rejectRefund(Refund $refund, string $reason, ?int $userId = null): Refund
    {
        return DB::transaction(function () use ($refund, $reason, $userId) {
            if ($refund->status !== 'pending') {
                throw new FinancialException("Impossible de rejeter un remboursement qui n'est plus en attente.");
            }

            $oldValues = $refund->toArray();

            $refund->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            AuditService::log('REFUND_REJECTED', $refund, [
                'rejection_reason' => $reason,
            ], $oldValues, $userId);

            return $refund->fresh();
        });
    }
}
