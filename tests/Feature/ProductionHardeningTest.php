<?php

namespace Tests\Feature;

use App\Contracts\NotificationProviderInterface;
use App\Exceptions\DuplicatePaymentException;
use App\Exceptions\InvalidPaymentAmountException;
use App\Exceptions\InvalidReservationStateException;
use App\Exceptions\OverpaymentException;
use App\Exceptions\StateTransitionException;
use App\Jobs\SendPaymentReminderJob;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\PaymentReminder;
use App\Models\PaymentSchedule;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\Notifications\NotificationManager;
use App\Services\PaymentReminderService;
use App\Services\ReservationService;
use App\Services\StateMachines\ReservationStateMachine;
use App\Services\StateMachines\UnitStateMachine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function setupReservationContext(string $slug = 'securisation-immo', float $price = 50000000): array
    {
        $tenant = Tenant::create(['slug' => $slug, 'name' => ucfirst($slug)]);
        session(['tenant_id' => $tenant->id]);

        $source = Source::create(['tenant_id' => $tenant->id, 'channel' => 'web', 'label' => 'Site Web']);
        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Fatou',
            'last_name' => 'Ndiaye',
            'phone_e164' => '+221771234567',
            'email' => 'fatou.ndiaye@example.com',
        ]);

        $property = Property::create([
            'tenant_id' => $tenant->id,
            'name' => 'Les Terrasses de Ngor',
            'location' => 'Ngor, Dakar',
            'property_type' => 'apartment',
        ]);

        $unit = Unit::create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
            'reference' => 'LOT-NGOR-101',
            'price' => $price,
            'status' => 'available',
        ]);

        $resService = new ReservationService();
        $reservation = $resService->createReservation([
            'tenant_id' => $tenant->id,
            'reference' => 'RES-NGOR-001',
            'contact_id' => $contact->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'total_amount' => $price,
            'deposit_amount' => $price * 0.05,
            'status' => 'option',
        ]);

        return compact('tenant', 'contact', 'property', 'unit', 'reservation', 'resService');
    }

    // =========================================================================
    // 1. SÉCURISATION DU CYCLE FINANCIER
    // =========================================================================

    #[Test]
    public function it_rejects_negative_or_zero_payment_amount(): void
    {
        $ctx = $this->setupReservationContext('reject-negative');
        $this->expectException(InvalidPaymentAmountException::class);

        $ctx['resService']->recordPayment($ctx['reservation'], [
            'amount' => 0,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]);
    }

    #[Test]
    public function it_rejects_payment_exceeding_remaining_balance(): void
    {
        $ctx = $this->setupReservationContext('reject-overpayment', 10000000);
        $this->expectException(OverpaymentException::class);

        // Tentative d'encaisser 15 000 000 FCFA sur un bien de 10 000 000 FCFA
        $ctx['resService']->recordPayment($ctx['reservation'], [
            'amount' => 15000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]);
    }

    #[Test]
    public function it_rejects_payment_on_cancelled_reservation(): void
    {
        $ctx = $this->setupReservationContext('reject-cancelled');
        $ctx['resService']->cancelReservation($ctx['reservation'], 'Désistement bancaire');

        $this->expectException(InvalidReservationStateException::class);

        $ctx['resService']->recordPayment($ctx['reservation']->fresh(), [
            'amount' => 5000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]);
    }

    #[Test]
    public function it_rejects_duplicate_proof_reference_within_the_same_tenant(): void
    {
        $ctx = $this->setupReservationContext('reject-duplicate-proof', 50000000);

        // Premier versement réussi
        $ctx['resService']->recordPayment($ctx['reservation'], [
            'amount' => 10000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'proof_reference' => 'VIR-SGBS-2026-0099',
        ]);

        // Seconde tentative avec le même bordereau de virement
        $this->expectException(DuplicatePaymentException::class);

        $ctx['resService']->recordPayment($ctx['reservation']->fresh(), [
            'amount' => 5000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'proof_reference' => 'VIR-SGBS-2026-0099',
        ]);
    }

    #[Test]
    public function it_tracks_refund_requirement_when_cancelling_with_prior_payments(): void
    {
        $ctx = $this->setupReservationContext('refund-on-cancel', 40000000);

        // Encaissement préalable d'un acompte
        $ctx['resService']->recordPayment($ctx['reservation'], [
            'amount' => 2000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]);

        $ctx['resService']->cancelReservation($ctx['reservation']->fresh(), 'Rétractation délai légal');

        $fresh = $ctx['reservation']->fresh();
        $this->assertEquals('cancelled', $fresh->status);
        $this->assertEquals('available', $ctx['unit']->fresh()->status);
        $this->assertStringContainsString('[REMBOURSEMENT REQUIS]', $fresh->notes);
        $this->assertStringContainsString('2 000 000 FCFA', $fresh->notes);
    }

    // =========================================================================
    // 2. RENFORCEMENT DES TRANSITIONS D'ÉTAT (STATE MACHINES)
    // =========================================================================

    #[Test]
    public function it_prevents_unauthorized_reservation_state_transitions(): void
    {
        // Une réservation annulée ne peut pas être passée à soldée
        $this->assertFalse(ReservationStateMachine::canTransition(
            ReservationStateMachine::STATE_CANCELLED,
            ReservationStateMachine::STATE_COMPLETED
        ));

        // Une réservation soldée ne peut pas être annulée
        $this->assertFalse(ReservationStateMachine::canTransition(
            ReservationStateMachine::STATE_COMPLETED,
            ReservationStateMachine::STATE_CANCELLED
        ));

        $this->expectException(StateTransitionException::class);
        ReservationStateMachine::validateTransition(
            ReservationStateMachine::STATE_CANCELLED,
            ReservationStateMachine::STATE_COMPLETED
        );
    }

    #[Test]
    public function it_prevents_unauthorized_unit_state_transitions(): void
    {
        // Un lot vendu ne peut plus redevenir disponible directement
        $this->assertFalse(UnitStateMachine::canTransition(
            UnitStateMachine::STATE_SOLD,
            UnitStateMachine::STATE_AVAILABLE
        ));

        $this->expectException(StateTransitionException::class);
        UnitStateMachine::validateTransition(
            UnitStateMachine::STATE_SOLD,
            UnitStateMachine::STATE_AVAILABLE
        );
    }

    // =========================================================================
    // 3. IDEMPOTENCE DES RELANCES & FILE D'ATTENTE ASYNCHRONE
    // =========================================================================

    #[Test]
    public function it_strictly_enforces_idempotency_key_to_prevent_duplicate_reminders(): void
    {
        $ctx = $this->setupReservationContext('idempotency-check');
        $schedule = $ctx['reservation']->schedules()->first();

        $schedule->update([
            'due_date' => Carbon::today()->addDays(7)->toDateString(),
            'status' => 'pending',
            'expected_amount' => 2500000,
            'paid_amount' => 0,
        ]);

        $service = new PaymentReminderService();

        // 1er passage du scheduler
        $run1 = $service->evaluateAndGenerateReminders($ctx['tenant']->id);
        $this->assertCount(1, $run1);
        $key = $run1[0]->idempotency_key;
        $this->assertNotNull($key);

        // 2e passage du scheduler le même jour
        $run2 = $service->evaluateAndGenerateReminders($ctx['tenant']->id);
        $this->assertCount(0, $run2);

        // Vérification absolue : un seul enregistrement en base
        $this->assertEquals(1, PaymentReminder::where('idempotency_key', $key)->count());
    }

    #[Test]
    public function it_dispatches_async_job_when_reminder_is_created(): void
    {
        Queue::fake();

        $ctx = $this->setupReservationContext('queue-test');
        $schedule = $ctx['reservation']->schedules()->first();

        $service = new PaymentReminderService();
        $reminder = $service->sendReminder(
            schedule: $schedule,
            triggerType: 'overdue_j7',
            channel: 'whatsapp'
        );

        $this->assertEquals('pending', $reminder->status);
        Queue::assertPushed(SendPaymentReminderJob::class, function ($job) use ($reminder) {
            return $job->reminderId === $reminder->id;
        });
    }

    #[Test]
    public function it_handles_job_execution_with_success_and_failure_scenarios(): void
    {
        $ctx = $this->setupReservationContext('job-execution');
        $schedule = $ctx['reservation']->schedules()->first();

        $reminder = PaymentReminder::create([
            'tenant_id' => $ctx['tenant']->id,
            'idempotency_key' => 'test-key-1',
            'payment_schedule_id' => $schedule->id,
            'reservation_id' => $ctx['reservation']->id,
            'contact_id' => $ctx['contact']->id,
            'trigger_type' => 'overdue_j7',
            'channel' => 'whatsapp',
            'status' => 'pending',
            'recipient' => '+221771234567',
            'message_content' => 'Message de test',
        ]);

        // Exécution réussie du Job
        $job = new SendPaymentReminderJob($reminder->id);
        $manager = new NotificationManager();
        $job->handle($manager);

        $fresh = $reminder->fresh();
        $this->assertEquals('sent', $fresh->status);
        $this->assertNotNull($fresh->sent_at);
        $this->assertNotNull($fresh->last_attempt_at);
        $this->assertNull($fresh->error_message);

        // Simulation d'un échec avec un faux provider qui lance une exception
        $failingManager = new class extends NotificationManager {
            public function send(PaymentReminder $reminder): bool {
                throw new \RuntimeException('Erreur réseau de passerelle SMS/WhatsApp');
            }
        };

        $failedReminder = PaymentReminder::create([
            'tenant_id' => $ctx['tenant']->id,
            'idempotency_key' => 'test-key-fail',
            'payment_schedule_id' => $schedule->id,
            'reservation_id' => $ctx['reservation']->id,
            'contact_id' => $ctx['contact']->id,
            'trigger_type' => 'overdue_j7',
            'channel' => 'sms',
            'status' => 'pending',
            'recipient' => '+221771234567',
            'message_content' => 'Message échec',
        ]);

        $failJob = new SendPaymentReminderJob($failedReminder->id);
        $failJob->handle($failingManager);

        $freshFailed = $failedReminder->fresh();
        $this->assertEquals('failed', $freshFailed->status);
        $this->assertEquals(1, $freshFailed->retry_count);
        $this->assertStringContainsString('Erreur réseau', $freshFailed->error_message);
    }
}
