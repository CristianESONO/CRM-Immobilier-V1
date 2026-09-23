<?php

namespace App\Listeners;

use App\Events\CommissionPaid;
use App\Events\ContractSigned;
use App\Events\KycDocumentVerified;
use App\Events\PaymentRecorded;
use App\Events\ReservationCreated;
use App\Services\Webhooks\OutboundWebhookDispatcherService;

class OutboundWebhookListener
{
    public function __construct(protected OutboundWebhookDispatcherService $dispatcher)
    {
    }

    public function handleReservationCreated(ReservationCreated $event): void
    {
        $this->dispatcher->dispatch('ReservationCreated', $event->reservation->tenant_id, [
            'event_id' => $event->getEventId(),
            'correlation_id' => $event->getCorrelationId(),
            'schema_version' => $event->getSchemaVersion(),
            'event' => 'ReservationCreated',
            'reservation_id' => $event->reservation->id,
            'reference' => $event->reservation->reference,
            'total_amount' => (float) $event->reservation->total_amount,
            'status' => $event->reservation->status,
            'created_at' => $event->reservation->created_at?->toIso8601String(),
        ]);
    }

    public function handlePaymentRecorded(PaymentRecorded $event): void
    {
        $this->dispatcher->dispatch('PaymentRecorded', $event->payment->tenant_id, [
            'event_id' => $event->getEventId(),
            'correlation_id' => $event->getCorrelationId(),
            'schema_version' => $event->getSchemaVersion(),
            'event' => 'PaymentRecorded',
            'payment_id' => $event->payment->id,
            'reservation_id' => $event->payment->reservation_id,
            'amount' => (float) $event->payment->amount,
            'payment_method' => $event->payment->payment_method,
            'proof_reference' => $event->payment->proof_reference,
            'recorded_at' => $event->payment->created_at?->toIso8601String(),
        ]);
    }

    public function handleContractSigned(ContractSigned $event): void
    {
        $this->dispatcher->dispatch('ContractSigned', $event->contract->tenant_id, [
            'event_id' => $event->getEventId(),
            'correlation_id' => $event->getCorrelationId(),
            'schema_version' => $event->getSchemaVersion(),
            'event' => 'ContractSigned',
            'contract_id' => $event->contract->id,
            'contract_number' => $event->contract->contract_number,
            'reservation_id' => $event->contract->reservation_id,
            'signed_at' => now()->toIso8601String(),
        ]);
    }

    public function handleCommissionPaid(CommissionPaid $event): void
    {
        $this->dispatcher->dispatch('CommissionPaid', $event->commission->tenant_id, [
            'event_id' => $event->getEventId(),
            'correlation_id' => $event->getCorrelationId(),
            'schema_version' => $event->getSchemaVersion(),
            'event' => 'CommissionPaid',
            'commission_id' => $event->commission->id,
            'referrer_id' => $event->commission->referrer_id,
            'commission_amount' => (float) $event->commission->commission_amount,
            'payment_reference' => $event->commission->payment_reference,
            'paid_at' => $event->commission->paid_at?->toIso8601String(),
        ]);
    }

    public function handleKycDocumentVerified(KycDocumentVerified $event): void
    {
        $this->dispatcher->dispatch('KycDocumentVerified', $event->buyerDocument->tenant_id, [
            'event_id' => $event->getEventId(),
            'correlation_id' => $event->getCorrelationId(),
            'schema_version' => $event->getSchemaVersion(),
            'event' => 'KycDocumentVerified',
            'document_id' => $event->buyerDocument->id,
            'document_type' => $event->buyerDocument->document_type,
            'status' => $event->buyerDocument->status,
            'verified_at' => now()->toIso8601String(),
        ]);
    }

    public function subscribe($events): array
    {
        return [
            ReservationCreated::class => 'handleReservationCreated',
            PaymentRecorded::class => 'handlePaymentRecorded',
            ContractSigned::class => 'handleContractSigned',
            CommissionPaid::class => 'handleCommissionPaid',
            KycDocumentVerified::class => 'handleKycDocumentVerified',
        ];
    }
}
