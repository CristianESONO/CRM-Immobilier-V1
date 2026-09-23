<?php

namespace App\Listeners;

use App\Events\ContactCreated;
use App\Events\ContractSigned;
use App\Events\KycDocumentVerified;
use App\Events\OperationalAlertCreated;
use App\Events\PaymentRecorded;
use App\Events\RefundCompleted;
use App\Events\ReservationCancelled;
use App\Events\ReservationCreated;
use App\Services\Workflows\WorkflowEngine;

class WorkflowEventListener
{
    public function __construct(protected WorkflowEngine $engine = new WorkflowEngine()) {}

    public function handleReservationCreated(ReservationCreated $event): void
    {
        $this->engine->trigger('ReservationCreated', $event->reservation, $event->reservation->tenant_id);
    }

    public function handleReservationCancelled(ReservationCancelled $event): void
    {
        $this->engine->trigger('ReservationCancelled', $event->reservation, $event->reservation->tenant_id);
    }

    public function handlePaymentRecorded(PaymentRecorded $event): void
    {
        $this->engine->trigger('PaymentRecorded', $event->payment, $event->payment->tenant_id);
    }

    public function handleContractSigned(ContractSigned $event): void
    {
        $this->engine->trigger('ContractSigned', $event->contract, $event->contract->tenant_id);
    }

    public function handleRefundCompleted(RefundCompleted $event): void
    {
        $this->engine->trigger('RefundCompleted', $event->refund, $event->refund->tenant_id);
    }

    public function handleKycDocumentVerified(KycDocumentVerified $event): void
    {
        $this->engine->trigger('KycDocumentVerified', $event->buyerDocument, $event->buyerDocument->tenant_id);
    }

    public function handleOperationalAlertCreated(OperationalAlertCreated $event): void
    {
        $this->engine->trigger('OperationalAlertCreated', $event->alert, $event->alert->tenant_id);
    }

    public function handleContactCreated(ContactCreated $event): void
    {
        $this->engine->trigger('ContactCreated', $event->contact, $event->contact->tenant_id);
    }

    public function subscribe($events): array
    {
        return [
            ReservationCreated::class => 'handleReservationCreated',
            ReservationCancelled::class => 'handleReservationCancelled',
            PaymentRecorded::class => 'handlePaymentRecorded',
            ContractSigned::class => 'handleContractSigned',
            RefundCompleted::class => 'handleRefundCompleted',
            KycDocumentVerified::class => 'handleKycDocumentVerified',
            OperationalAlertCreated::class => 'handleOperationalAlertCreated',
            ContactCreated::class => 'handleContactCreated',
        ];
    }
}
