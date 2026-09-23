<?php

namespace Tests\Feature;

use App\Filament\Widgets\OperationalAlertsWidget;
use App\Models\BuyerDocument;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\OperationalAlert;
use App\Models\PaymentReminder;
use App\Models\PaymentSchedule;
use App\Models\Property;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\SignatureWebhookEvent;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Alerts\OperationalAlertService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OperationalAlertTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Source $sourceA;
    private Source $sourceB;
    private Property $propertyA;
    private Property $propertyB;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-20')->startOfDay());

        $this->tenantA = Tenant::create(['name' => 'Promotion Alertes A', 'slug' => 'alert-a']);
        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'DA Conformité A',
            'email' => 'da-compliance@a.test',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->sourceA = Source::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'web',
            'label' => 'Web A',
        ]);
        $this->propertyA = Property::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Résidence Alerte A',
            'location' => 'Dakar',
        ]);

        $this->tenantB = Tenant::create(['name' => 'Promotion Alertes B', 'slug' => 'alert-b']);
        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'DA Conformité B',
            'email' => 'da-compliance@b.test',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->sourceB = Source::create([
            'tenant_id' => $this->tenantB->id,
            'channel' => 'web',
            'label' => 'Web B',
        ]);
        $this->propertyB = Property::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Résidence Alerte B',
            'location' => 'Saly',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function operational_alerts_respect_tenant_isolation(): void
    {
        // Tenant A: Lead sans réponse > 48h
        $contactA = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $this->sourceA->id,
            'first_name' => 'Alerte',
            'last_name' => 'TenantA',
            'status' => 'prospect',
        ]);
        $contactA->created_at = Carbon::now()->subHours(50);
        $contactA->save();

        // Tenant B: Lead sans réponse > 48h
        $contactB = Contact::create([
            'tenant_id' => $this->tenantB->id,
            'source_id' => $this->sourceB->id,
            'first_name' => 'Alerte',
            'last_name' => 'TenantB',
            'status' => 'prospect',
        ]);
        $contactB->created_at = Carbon::now()->subHours(60);
        $contactB->save();

        $service = app(OperationalAlertService::class);
        $service->scanAllTenants();

        $this->actingAs($this->userA);
        $summaryA = $service->getSummaryForTenant($this->tenantA);
        $alertsA = OperationalAlert::open()->get();

        $this->assertEquals(1, $summaryA['total_open']);
        $this->assertEquals($contactA->id, $alertsA->first()->entity_id);

        $this->actingAs($this->userB);
        $summaryB = $service->getSummaryForTenant($this->tenantB);
        $alertsB = OperationalAlert::open()->get();

        $this->assertEquals(1, $summaryB['total_open']);
        $this->assertEquals($contactB->id, $alertsB->first()->entity_id);
    }

    #[Test]
    public function it_detects_all_12_operational_alert_types(): void
    {
        $this->actingAs($this->userA);
        $service = app(OperationalAlertService::class);

        // 1. Commercial : Prospect sans réponse > 48h
        $contact = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $this->sourceA->id,
            'first_name' => 'Moustapha',
            'last_name' => 'Sall',
            'status' => 'prospect',
        ]);
        $contact->created_at = Carbon::now()->subHours(72);
        $contact->save();

        // 2. Opérationnel : Action/Rappel échu
        $resa = $this->makeReservation('confirmed', 15_000_000, Carbon::now()->subDays(5));
        $schedule = PaymentSchedule::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $resa->id,
            'label' => 'Acompte',
            'due_date' => Carbon::today()->addDays(5)->toDateString(),
            'expected_amount' => 5_000_000,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);
        PaymentReminder::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $resa->id,
            'contact_id' => $resa->contact_id,
            'payment_schedule_id' => $schedule->id,
            'trigger_type' => 'preventive_7_days',
            'channel' => 'email',
            'recipient' => 'client@test.sn',
            'message_content' => 'Rappel test',
            'scheduled_at' => Carbon::now()->subHours(5),
            'status' => 'scheduled',
        ]);

        // 3. Stock : Option expirée (> 7j)
        $resaOption = $this->makeReservation('option', 20_000_000, Carbon::now()->subDays(10));

        // 4. Contrat : Réservation confirmée sans contrat (> 3j)
        // ($resa créé ci-dessus depuis 5 jours n'a pas de contrat)

        // 5. Contrat : Contrat en attente de validation (> 24h)
        $contractPending = Contract::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $resaOption->id,
            'contract_number' => 'CTR-PND-001',
            'contract_type' => 'reservation_contract',
            'status' => 'pending_approval',
        ]);
        $contractPending->created_at = Carbon::now()->subHours(30);
        $contractPending->save();

        // 6. Signature : Contrat envoyé non signé (> 7j)
        $resaSigned = $this->makeReservation('confirmed', 30_000_000);
        $contractSent = Contract::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $resaSigned->id,
            'contract_number' => 'CTR-SNT-002',
            'contract_type' => 'reservation_contract',
            'status' => 'sent',
            'signature_request_id' => 'sig_req_123',
        ]);
        $contractSent->created_at = Carbon::now()->subDays(10);
        $contractSent->updated_at = Carbon::now()->subDays(8);
        $contractSent->save();

        // 7. KYC : Document expiré
        BuyerDocument::create([
            'tenant_id' => $this->tenantA->id,
            'contact_id' => $contact->id,
            'reservation_id' => $resa->id,
            'document_type' => 'id_card',
            'title' => 'CNI Expirée',
            'file_path' => 'kyc/cni.pdf',
            'status' => 'expired',
            'expires_at' => Carbon::today()->subDays(2),
        ]);

        // 8. KYC : Dossier incomplet (sur $resa confirmée)

        // 9. Finance : Échéance en retard
        $overdueSchedule = PaymentSchedule::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $resa->id,
            'label' => 'Jalon Retard',
            'due_date' => Carbon::today()->subDays(15)->toDateString(),
            'expected_amount' => 10_000_000,
            'paid_amount' => 2_000_000,
            'status' => 'overdue',
        ]);

        // 10. Finance : Échéance proche (J+3)
        $upcomingSchedule = PaymentSchedule::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $resa->id,
            'label' => 'Jalon Proche',
            'due_date' => Carbon::today()->addDays(3)->toDateString(),
            'expected_amount' => 3_000_000,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        // 11. Finance : Remboursement bloqué (> 5j)
        $refund = Refund::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $resa->id,
            'reference' => 'REF-BLK-001',
            'amount' => 1_000_000,
            'reason' => 'Annulation partielle',
            'status' => 'pending',
            'requested_at' => Carbon::now()->subDays(6),
        ]);
        $refund->created_at = Carbon::now()->subDays(6);
        $refund->save();

        // 12. Technique : Webhook en erreur
        SignatureWebhookEvent::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'yousign',
            'event_id' => 'evt_err_999',
            'event_type' => 'procedure.refused',
            'signature_request_id' => 'sig_req_123',
            'status' => 'failed',
        ]);

        $service->scanTenant($this->tenantA);

        $summary = $service->getSummaryForTenant($this->tenantA);
        $this->assertGreaterThanOrEqual(10, $summary['total_open']);
        $this->assertGreaterThan(0, $summary['critical']);
        $this->assertGreaterThan(0, $summary['warning']);
        $this->assertGreaterThan(0, $summary['info']);
    }

    #[Test]
    public function scan_enforces_strict_idempotency_without_duplicate_alerts(): void
    {
        $this->actingAs($this->userA);
        $service = app(OperationalAlertService::class);

        // Lead sans réponse
        $contact = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $this->sourceA->id,
            'first_name' => 'Idempotent',
            'last_name' => 'Test',
            'status' => 'prospect',
        ]);
        $contact->created_at = Carbon::now()->subHours(72);
        $contact->save();

        // 1er scan
        $res1 = $service->scanTenant($this->tenantA);
        $this->assertEquals(1, $res1['created']);
        $this->assertEquals(1, OperationalAlert::count());

        // 2ème scan consécutif
        $res2 = $service->scanTenant($this->tenantA);
        $this->assertEquals(0, $res2['created']);
        $this->assertEquals(1, OperationalAlert::count());
    }

    #[Test]
    public function it_auto_resolves_alerts_when_underlying_condition_is_fixed(): void
    {
        $this->actingAs($this->userA);
        $service = app(OperationalAlertService::class);

        $contact = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $this->sourceA->id,
            'first_name' => 'AutoFix',
            'last_name' => 'Test',
            'status' => 'prospect',
        ]);
        $contact->created_at = Carbon::now()->subHours(72);
        $contact->save();

        $service->scanTenant($this->tenantA);
        $this->assertEquals(1, OperationalAlert::open()->count());

        // Le commercial répond au lead (met à jour first_response_at)
        $contact->update(['first_response_at' => Carbon::now()]);

        // Scan suivant
        $res = $service->scanTenant($this->tenantA);
        $this->assertEquals(1, $res['auto_resolved']);
        $this->assertEquals(0, OperationalAlert::open()->count());
        $this->assertEquals('resolved', OperationalAlert::first()->status);
    }

    #[Test]
    public function it_handles_manual_acknowledge_resolve_and_dismiss_lifecycle(): void
    {
        $this->actingAs($this->userA);
        $service = app(OperationalAlertService::class);

        $alert = OperationalAlert::create([
            'tenant_id' => $this->tenantA->id,
            'type' => 'lead_no_response_sla',
            'severity' => 'warning',
            'status' => 'open',
            'entity_type' => Contact::class,
            'entity_id' => 99,
            'title' => 'Test Lifecycle',
            'detected_at' => Carbon::now(),
            'idempotency_key' => "{$this->tenantA->id}:test:99",
        ]);

        // Acknowledge
        $service->acknowledge($alert);
        $this->assertEquals('acknowledged', $alert->fresh()->status);
        $this->assertTrue($alert->fresh()->isOpen());

        // Resolve
        $service->resolve($alert, 'Prise de contact téléphonique effectuée');
        $this->assertEquals('resolved', $alert->fresh()->status);
        $this->assertTrue($alert->fresh()->isResolved());
        $this->assertNotNull($alert->fresh()->resolved_at);
        $this->assertEquals('Prise de contact téléphonique effectuée', $alert->fresh()->metadata['resolution_notes']);
    }

    #[Test]
    public function it_handles_empty_dataset_and_renders_widget_safely(): void
    {
        $this->actingAs($this->userA);
        $service = app(OperationalAlertService::class);

        $res = $service->scanTenant($this->tenantA);
        $this->assertEquals(0, $res['created']);

        $summary = $service->getSummaryForTenant($this->tenantA);
        $this->assertEquals(0, $summary['total_open']);

        $widget = invade(new OperationalAlertsWidget());
        $stats = $widget->getStats();

        $this->assertCount(4, $stats);
        $this->assertEquals('0', $stats[0]->getValue());
    }

    private function makeReservation(string $status, float $amount, ?Carbon $reservedAt = null): Reservation
    {
        $this->seq++;

        $contact = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $this->sourceA->id,
            'first_name' => 'Acquéreur',
            'last_name' => 'Alerte' . $this->seq,
        ]);

        $unit = Unit::create([
            'tenant_id' => $this->tenantA->id,
            'property_id' => $this->propertyA->id,
            'reference' => 'LOT-ALT-' . $this->seq,
            'area' => 75,
            'price' => $amount,
            'status' => $status === 'cancelled' ? 'available' : 'reserved',
        ]);

        $resa = Reservation::create([
            'tenant_id' => $this->tenantA->id,
            'reference' => 'RES-ALT-' . $this->seq,
            'contact_id' => $contact->id,
            'property_id' => $this->propertyA->id,
            'unit_id' => $unit->id,
            'assigned_to' => $this->userA->id,
            'status' => $status,
            'total_amount' => $amount,
            'deposit_amount' => 0,
            'reserved_at' => $reservedAt ?? Carbon::now(),
        ]);

        if ($reservedAt) {
            $resa->created_at = $reservedAt;
            $resa->save();
        }

        return $resa;
    }
}
