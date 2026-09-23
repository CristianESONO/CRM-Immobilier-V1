<?php

namespace App\Listeners;

use App\Events\ContractSigned;
use App\Events\KycDocumentVerified;
use App\Events\PaymentRecorded;
use App\Events\RefundCompleted;
use App\Events\ReservationCancelled;
use App\Events\ReservationCreated;
use App\Services\AuditService;

class AuditDomainEventListener
{
    public function handleReservationCreated(ReservationCreated $event): void
    {
        AuditService::log('RESERVATION_CREATED', $event->reservation, [
            'reference' => $event->reservation->reference,
            'total_amount' => $event->reservation->total_amount,
            'unit_id' => $event->reservation->unit_id,
            'contact_id' => $event->reservation->contact_id,
        ], null, $event->user?->id, $event->reservation->tenant_id);
    }

    public function handleReservationCancelled(ReservationCancelled $event): void
    {
        AuditService::log('RESERVATION_CANCELLED', $event->reservation, [
            'reference' => $event->reservation->reference,
            'reason' => $event->reason,
            'cancelled_at' => now()->toDateTimeString(),
        ], null, $event->user?->id, $event->reservation->tenant_id);
    }

    public function handlePaymentRecorded(PaymentRecorded $event): void
    {
        AuditService::log('PAYMENT_RECORDED', $event->payment, [
            'amount' => $event->payment->amount,
            'payment_method' => $event->payment->payment_method,
            'proof_reference' => $event->payment->proof_reference,
            'reservation_id' => $event->payment->reservation_id,
        ], null, $event->user?->id, $event->payment->tenant_id);
    }

    public function handleContractSigned(ContractSigned $event): void
    {
        AuditService::log('CONTRACT_SIGNED', $event->contract, [
            'contract_number' => $event->contract->contract_number,
            'signed_version' => $event->contract->current_version,
            'signed_pdf_path' => $event->signedPdfPath,
            'signed_at' => now()->toDateTimeString(),
        ], null, $event->user?->id, $event->contract->tenant_id);
    }

    public function handleRefundCompleted(RefundCompleted $event): void
    {
        AuditService::log('REFUND_COMPLETED', $event->refund, [
            'reference' => $event->refund->reference,
            'amount' => $event->refund->amount,
            'processed_at' => now()->toDateTimeString(),
        ], null, $event->user?->id, $event->refund->tenant_id);
    }

    public function handleKycDocumentVerified(KycDocumentVerified $event): void
    {
        AuditService::log('BUYER_DOCUMENT_VERIFIED', $event->buyerDocument, [
            'document_type' => $event->buyerDocument->document_type,
            'title' => $event->buyerDocument->title,
            'verified_at' => now()->toDateTimeString(),
        ], null, $event->user?->id, $event->buyerDocument->tenant_id);
    }

    /**
     * Enregistre l'ensemble des écouteurs du subscriber.
     */
    public function subscribe($events): array
    {
        return [
            ReservationCreated::class => 'handleReservationCreated',
            ReservationCancelled::class => 'handleReservationCancelled',
            PaymentRecorded::class => 'handlePaymentRecorded',
            ContractSigned::class => 'handleContractSigned',
            RefundCompleted::class => 'handleRefundCompleted',
            KycDocumentVerified::class => 'handleKycDocumentVerified',
        ];
    }
}
