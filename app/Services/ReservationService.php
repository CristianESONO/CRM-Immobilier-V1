<?php

namespace App\Services;

use App\Events\PaymentRecorded;
use App\Events\ReservationCancelled;
use App\Events\ReservationCreated;
use App\Exceptions\DuplicatePaymentException;
use App\Exceptions\InvalidPaymentAmountException;
use App\Exceptions\InvalidReservationStateException;
use App\Exceptions\OverpaymentException;
use App\Exceptions\StateTransitionException;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Reservation;
use App\Models\Unit;
use App\Services\StateMachines\ReservationStateMachine;
use App\Services\StateMachines\UnitStateMachine;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    /**
     * VEFA standard payment schedule percentages.
     */
    public const VEFA_DEFAULT_SCHEDULE = [
        ['label' => 'Dépôt de garantie', 'percentage' => 5],
        ['label' => 'Achèvement des fondations', 'percentage' => 30],
        ['label' => 'Mise hors d\'eau (gros œuvre)', 'percentage' => 35],
        ['label' => 'Second œuvre / Finitions', 'percentage' => 25],
        ['label' => 'Remise des clés (solde final)', 'percentage' => 5],
    ];

    /**
     * Create a reservation with pessimistic locking to prevent double-booking.
     *
     * @param array $data  Reservation fields
     * @param array $scheduleItems  Custom schedule lines; if empty, the VEFA default is used
     * @return Reservation
     * @throws \Exception  If the unit is already reserved or unavailable
     */
    public function createReservation(array $data, array $scheduleItems = []): Reservation
    {
        return DB::transaction(function () use ($data, $scheduleItems) {
            // Pessimistic lock: prevents concurrent reservations on the same unit
            $unit = Unit::lockForUpdate()->findOrFail($data['unit_id']);

            if ($unit->status !== 'available') {
                throw new \Exception(
                    "Le lot '{$unit->reference}' n'est plus disponible. Il a été réservé ou vendu entre temps."
                );
            }

            // Validate unit state transition to reserved
            UnitStateMachine::validateTransition($unit->status, UnitStateMachine::STATE_RESERVED);

            // Generate a unique reference for this reservation
            $tenantId = $data['tenant_id'];
            $count = Reservation::where('tenant_id', $tenantId)->count() + 1;
            $reference = sprintf('RES-%s-%03d', now()->format('Y'), $count);

            $reservation = Reservation::create(array_merge($data, [
                'reference' => $reference,
                'status' => ReservationStateMachine::STATE_OPTION,
            ]));

            // Lock the unit immediately
            $unit->update(['status' => UnitStateMachine::STATE_RESERVED]);

            // Create payment schedule lines
            $schedules = empty($scheduleItems) ? self::VEFA_DEFAULT_SCHEDULE : $scheduleItems;
            $totalAmount = (float) $data['total_amount'];

            foreach ($schedules as $schedule) {
                $expectedAmount = isset($schedule['expected_amount'])
                    ? (float) $schedule['expected_amount']
                    : round($totalAmount * ($schedule['percentage'] / 100), 2);

                PaymentSchedule::create([
                    'tenant_id' => $tenantId,
                    'reservation_id' => $reservation->id,
                    'label' => $schedule['label'],
                    'due_date' => $schedule['due_date'] ?? null,
                    'percentage' => $schedule['percentage'] ?? null,
                    'expected_amount' => $expectedAmount,
                    'paid_amount' => 0,
                    'status' => 'pending',
                ]);
            }

            $created = $reservation->fresh(['schedules', 'unit', 'contact', 'property']);

            AuditService::log('RESERVATION_CREATED', $created, [
                'reference' => $created->reference,
                'total_amount' => $created->total_amount,
                'unit_ref' => $unit->reference,
                'contact_id' => $created->contact_id,
            ]);

            ReservationCreated::dispatch($created, auth()->user());

            return $created;
        });
    }

    /**
     * Record a payment against a reservation with pessimistic locking,
     * overpayment checks, idempotency on proof_reference and state machine validation.
     *
     * @param Reservation $reservation
     * @param array $paymentData
     * @return Payment
     * @throws InvalidReservationStateException
     * @throws InvalidPaymentAmountException
     * @throws OverpaymentException
     * @throws DuplicatePaymentException
     */
    public function recordPayment(Reservation $reservation, array $paymentData): Payment
    {
        return DB::transaction(function () use ($reservation, $paymentData) {
            // 1. Pessimistic lock on reservation to prevent concurrent payment race conditions
            $res = Reservation::lockForUpdate()->findOrFail($reservation->id);

            // 2. Reservation state check
            if (in_array($res->status, [ReservationStateMachine::STATE_CANCELLED, ReservationStateMachine::STATE_COMPLETED])) {
                throw new InvalidReservationStateException(
                    "Impossible d'enregistrer un paiement : la réservation est dans l'état '{$res->status}'."
                );
            }

            // 3. Validate payment amount (strictly positive)
            $amount = (float) ($paymentData['amount'] ?? 0);
            if ($amount <= 0) {
                throw new InvalidPaymentAmountException("Le montant du versement doit être strictement positif.");
            }

            // 4. Overpayment check
            $currentPaid = (float) $res->payments()->where('status', 'validated')->sum('amount');
            $remaining = max(0, (float) $res->total_amount - $currentPaid);
            if ($amount > $remaining) {
                throw new OverpaymentException(
                    sprintf(
                        "Le montant du versement (%s FCFA) excède le solde restant dû (%s FCFA).",
                        number_format($amount, 0, ',', ' '),
                        number_format($remaining, 0, ',', ' ')
                    )
                );
            }

            // 5. Check duplicate payment reference within the same tenant
            if (!empty($paymentData['proof_reference'])) {
                $exists = Payment::where('tenant_id', $res->tenant_id)
                    ->where('proof_reference', $paymentData['proof_reference'])
                    ->where('status', 'validated')
                    ->exists();

                if ($exists) {
                    throw new DuplicatePaymentException(
                        "Un paiement validé avec le justificatif '{$paymentData['proof_reference']}' a déjà été enregistré."
                    );
                }
            }

            $tenantId = $res->tenant_id;
            $count = Payment::where('tenant_id', $tenantId)->count() + 1;
            $reference = sprintf('PAY-%s-%03d', now()->format('Y'), $count);

            $payment = Payment::create(array_merge($paymentData, [
                'tenant_id' => $tenantId,
                'reservation_id' => $res->id,
                'reference' => $reference,
                'status' => $paymentData['status'] ?? 'validated',
            ]));

            // Refresh the target schedule line status if provided
            if (!empty($paymentData['payment_schedule_id'])) {
                $schedule = PaymentSchedule::find($paymentData['payment_schedule_id']);
                if ($schedule) {
                    $schedule->refreshStatus();
                }
            }

            // 6. Recalculate total paid
            $newTotalPaid = $currentPaid + $amount;
            if ($newTotalPaid >= (float) $res->total_amount) {
                ReservationStateMachine::validateTransition($res->status, ReservationStateMachine::STATE_COMPLETED);
                $res->update(['status' => ReservationStateMachine::STATE_COMPLETED]);

                $unit = $res->unit()->lockForUpdate()->first();
                if ($unit) {
                    UnitStateMachine::validateTransition($unit->status, UnitStateMachine::STATE_SOLD);
                    $unit->update(['status' => UnitStateMachine::STATE_SOLD]);
                }
            }

            AuditService::log('PAYMENT_RECORDED', $payment, [
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'reservation_reference' => $res->reference,
                'status' => $payment->status,
            ], null, null, $res->tenant_id);

            PaymentRecorded::dispatch($payment, auth()->user());

            return $payment;
        });
    }

    /**
     * Cancel a reservation with state machine enforcement, refund logging,
     * and immediate lot release back to available stock.
     *
     * @param Reservation $reservation
     * @param string $reason
     * @return void
     * @throws StateTransitionException
     */
    public function cancelReservation(Reservation $reservation, string $reason): void
    {
        DB::transaction(function () use ($reservation, $reason) {
            $res = Reservation::lockForUpdate()->findOrFail($reservation->id);
            $oldStatus = $res->status;

            // Cannot cancel if already completed / sold
            ReservationStateMachine::validateTransition($res->status, ReservationStateMachine::STATE_CANCELLED);

            $unit = $res->unit()->lockForUpdate()->first();
            if ($unit) {
                UnitStateMachine::validateTransition($unit->status, UnitStateMachine::STATE_AVAILABLE);
                $unit->update(['status' => UnitStateMachine::STATE_AVAILABLE]);
            }

            $paid = (float) $res->payments()->where('status', 'validated')->sum('amount');
            $notes = $res->notes;
            if ($paid > 0) {
                $formatted = number_format($paid, 0, ',', ' ') . ' FCFA';
                $notes = trim(($notes ? $notes . "\n" : '') . "[REMBOURSEMENT REQUIS] Somme encaissée à restituer suite à annulation : {$formatted}.");

                // Déclenchement automatique du module Refund
                $refundService = new RefundService();
                $refundService->requestRefund($res, $paid, "Remboursement suite à annulation du dossier {$res->reference} : {$reason}");
            }

            $res->update([
                'status' => ReservationStateMachine::STATE_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'notes' => $notes,
            ]);

            AuditService::log('RESERVATION_CANCELLED', $res, [
                'reason' => $reason,
                'paid_to_refund' => $paid,
            ], ['status' => $oldStatus], null, $res->tenant_id);

            ReservationCancelled::dispatch($res, $reason, auth()->user());
        });
    }
}
