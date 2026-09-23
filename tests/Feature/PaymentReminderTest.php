<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Payment;
use App\Models\PaymentReminder;
use App\Models\PaymentSchedule;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\PaymentReminderService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentReminderTest extends TestCase
{
    use RefreshDatabase;

    private function setupReservation(string $slug = 'promoteur-immo'): array
    {
        $tenant = Tenant::create(['slug' => $slug, 'name' => ucfirst($slug)]);
        session(['tenant_id' => $tenant->id]);

        $user = User::factory()->create(['email' => "agent@{$slug}.com"]);
        $source = Source::create(['tenant_id' => $tenant->id, 'channel' => 'whatsapp', 'label' => 'WhatsApp Ads']);

        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Moussa',
            'last_name' => 'Diop',
            'phone' => '+221770001122',
            'email' => 'moussa.diop@example.com',
        ]);

        $property = Property::create([
            'tenant_id' => $tenant->id,
            'name' => 'Les Jardins des Almadies',
            'location' => 'Almadies, Dakar',
            'property_type' => 'apartment',
        ]);

        $unit = Unit::create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
            'reference' => 'LOT-ALM-204',
            'price' => 100000000,
            'status' => 'available',
        ]);

        $resService = new ReservationService();
        $reservation = $resService->createReservation([
            'tenant_id' => $tenant->id,
            'reference' => 'RES-TEST-001',
            'contact_id' => $contact->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'total_amount' => 100000000,
            'deposit_amount' => 5000000,
            'status' => 'confirmed',
        ]);

        return compact('tenant', 'user', 'contact', 'property', 'unit', 'reservation');
    }

    #[Test]
    public function it_generates_preventive_reminder_seven_days_before_due_date(): void
    {
        $data = $this->setupReservation('promoteur-rappel-j7');
        $reservation = $data['reservation'];
        $schedule = $reservation->schedules()->first();

        // Régler l'échéance à J+7
        $schedule->update([
            'due_date' => Carbon::today()->addDays(7)->toDateString(),
            'status' => 'pending',
            'expected_amount' => 5000000,
            'paid_amount' => 0,
        ]);

        $service = new PaymentReminderService();
        $reminders = $service->evaluateAndGenerateReminders($data['tenant']->id);

        $this->assertCount(1, $reminders);
        $reminder = $reminders[0];

        $this->assertEquals('preventive_j7', $reminder->trigger_type);
        $this->assertEquals('whatsapp', $reminder->channel);
        $this->assertEquals('+221770001122', $reminder->recipient);
        $this->assertStringContainsString('Les Jardins des Almadies', $reminder->message_content);
        $this->assertStringContainsString('LOT-ALM-204', $reminder->message_content);
        $this->assertStringContainsString('5 000 000 FCFA', $reminder->message_content);
        $this->assertDatabaseHas('payment_reminders', [
            'id' => $reminder->id,
            'trigger_type' => 'preventive_j7',
            'tenant_id' => $data['tenant']->id,
        ]);
    }

    #[Test]
    public function it_prevents_duplicate_reminders_for_the_same_trigger(): void
    {
        $data = $this->setupReservation('promoteur-anti-spam');
        $reservation = $data['reservation'];
        $schedule = $reservation->schedules()->first();

        $schedule->update([
            'due_date' => Carbon::today()->addDays(7)->toDateString(),
            'status' => 'pending',
            'expected_amount' => 5000000,
            'paid_amount' => 0,
        ]);

        $service = new PaymentReminderService();

        // 1er appel
        $firstRun = $service->evaluateAndGenerateReminders($data['tenant']->id);
        $this->assertCount(1, $firstRun);

        // 2e appel le même jour : ne doit rien générer en double
        $secondRun = $service->evaluateAndGenerateReminders($data['tenant']->id);
        $this->assertCount(0, $secondRun);

        $this->assertEquals(1, PaymentReminder::where('payment_schedule_id', $schedule->id)->count());
    }

    #[Test]
    public function it_generates_overdue_reminders_and_updates_schedule_status(): void
    {
        $data = $this->setupReservation('promoteur-overdue');
        $reservation = $data['reservation'];
        $schedule = $reservation->schedules()->first();

        // Échéance en retard de 8 jours (zone J+7)
        $schedule->update([
            'due_date' => Carbon::today()->subDays(8)->toDateString(),
            'status' => 'pending',
            'expected_amount' => 30000000,
            'paid_amount' => 0,
        ]);

        $service = new PaymentReminderService();
        $reminders = $service->evaluateAndGenerateReminders($data['tenant']->id);

        $this->assertCount(1, $reminders);
        $this->assertEquals('overdue_j7', $reminders[0]->trigger_type);

        // Le statut de l'échéance doit être passé en overdue
        $this->assertEquals('overdue', $schedule->fresh()->status);
    }

    #[Test]
    public function it_records_manual_reminder_initiated_by_user(): void
    {
        $data = $this->setupReservation('promoteur-manual');
        $reservation = $data['reservation'];
        $schedule = $reservation->schedules()->first();
        $user = $data['user'];

        $service = new PaymentReminderService();
        $reminder = $service->sendReminder(
            schedule: $schedule,
            triggerType: 'manual',
            channel: 'email',
            customMessage: 'Message spécifique convenu lors de notre réunion.',
            userId: $user->id
        );

        $this->assertNotNull($reminder->id);
        $this->assertEquals('manual', $reminder->trigger_type);
        $this->assertEquals('email', $reminder->channel);
        $this->assertEquals($user->id, $reminder->sent_by_user_id);
        $this->assertEquals('Message spécifique convenu lors de notre réunion.', $reminder->message_content);
    }

    #[Test]
    public function it_executes_artisan_command_successfully(): void
    {
        $data = $this->setupReservation('promoteur-artisan');
        $reservation = $data['reservation'];
        $schedule = $reservation->schedules()->first();

        $schedule->update([
            'due_date' => Carbon::today()->addDays(7)->toDateString(),
            'status' => 'pending',
            'expected_amount' => 5000000,
            'paid_amount' => 0,
        ]);

        $this->artisan('crm:send-payment-reminders', ['--tenant' => $data['tenant']->id])
            ->expectsOutputToContain('Traitement des relances automatiques')
            ->expectsOutputToContain('Terminé. Total de relances générées : 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('payment_reminders', [
            'payment_schedule_id' => $schedule->id,
            'trigger_type' => 'preventive_j7',
        ]);
    }

    #[Test]
    public function it_respects_tenant_isolation_when_generating_reminders(): void
    {
        $tenantA = $this->setupReservation('tenant-alpha');
        $tenantB = $this->setupReservation('tenant-beta');

        $scheduleA = PaymentSchedule::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('reservation_id', $tenantA['reservation']->id)
            ->first();

        $scheduleB = PaymentSchedule::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('reservation_id', $tenantB['reservation']->id)
            ->first();

        $scheduleA->update(['due_date' => Carbon::today()->addDays(7)->toDateString()]);
        $scheduleB->update(['due_date' => Carbon::today()->addDays(7)->toDateString()]);

        $service = new PaymentReminderService();

        // Relances pour Tenant A uniquement
        $remindersA = $service->evaluateAndGenerateReminders($tenantA['tenant']->id);
        $this->assertCount(1, $remindersA);
        $this->assertEquals($tenantA['tenant']->id, $remindersA[0]->tenant_id);

        // Tenant B ne doit pas avoir de relances encore
        $this->assertEquals(0, PaymentReminder::where('tenant_id', $tenantB['tenant']->id)->count());
    }
}
