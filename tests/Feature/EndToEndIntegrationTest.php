<?php

namespace Tests\Feature;

use App\Events\ContractSigned;
use App\Events\KycDocumentVerified;
use App\Events\PaymentRecorded;
use App\Events\ReservationCreated;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\DocumentRequirement;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Documents\BuyerDocumentService;
use App\Services\PropertyMatchingService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EndToEndIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $director;
    private User $commercial;
    private Source $source;
    private Property $property;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tenant = Tenant::create(['name' => 'Promotion Prestige', 'slug' => 'prestige']);

        $this->director = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Directeur Général',
            'email' => 'dg@prestige.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->commercial = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Jean Commercial',
            'email' => 'jean@prestige.com',
            'password' => bcrypt('secret'),
            'role' => 'commercial',
            'is_active' => true,
        ]);

        $this->source = Source::create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'web',
            'label' => 'Portail Web Promoteur',
        ]);

        $this->property = Property::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Les Jardins d\'Almadies',
            'location' => 'Dakar Almadies',
            'property_type' => 'apartment',
            'status' => 'available',
        ]);

        $this->unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->property->id,
            'reference' => 'LOT-JA-301',
            'area' => 125.0,
            'price' => 80000000,
            'status' => 'available',
        ]);
    }

    #[Test]
    public function it_executes_the_entire_real_estate_sale_lifecycle_end_to_end(): void
    {
        // =========================================================================
        // ÉTAPE 1 : Capture du Prospect & Qualification
        // =========================================================================
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'first_name' => 'Oumar',
            'last_name' => 'Sylla',
            'email' => 'oumar.sylla@example.com',
            'phone_e164' => '+221771112233',
            'buyer_type' => 'individual',
            'status' => 'nouveau',
            'budget_min' => 70000000,
            'budget_max' => 90000000,
            'property_type' => 'apartment',
            'district' => 'Almadies',
        ]);

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'email' => 'oumar.sylla@example.com']);

        // =========================================================================
        // ÉTAPE 2 : Moteur de Rapprochement Intelligent (Matching)
        // =========================================================================
        $matchingService = app(PropertyMatchingService::class);
        $matches = $matchingService->findMatchingUnitsForContact($contact);

        $this->assertNotEmpty($matches);
        $this->assertEquals($this->unit->id, $matches->first()['unit']->id);
        $this->assertGreaterThanOrEqual(80, $matches->first()['score']);

        // =========================================================================
        // ÉTAPE 3 : Réservation avec Verrouillage du Lot & Échéancier VEFA
        // =========================================================================
        $reservationService = app(ReservationService::class);
        $reservation = $reservationService->createReservation([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'assigned_to' => $this->commercial->id,
            'total_amount' => 80000000,
            'deposit_amount' => 8000000,
        ]);

        $this->unit->refresh();
        $this->assertEquals('reserved', $this->unit->status);
        $this->assertEquals('option', $reservation->status);
        $this->assertCount(5, $reservation->schedules);

        // Audit Log émis via Domain Event
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'auditable_type' => Reservation::class,
            'auditable_id' => $reservation->id,
            'action' => 'RESERVATION_CREATED',
        ]);

        // =========================================================================
        // ÉTAPE 4 : Encaissement du Dépôt de Garantie
        // =========================================================================
        $depositSchedule = $reservation->schedules->first();
        $payment = $reservationService->recordPayment($reservation, [
            'amount' => 8000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'proof_reference' => 'VIR-DEP-2026-001',
            'payment_schedule_id' => $depositSchedule->id,
        ]);

        $this->assertEquals(8000000, $reservation->fresh()->total_paid);
        $this->assertEquals(72000000, $reservation->fresh()->remaining_balance);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'auditable_type' => \App\Models\Payment::class,
            'auditable_id' => $payment->id,
            'action' => 'PAYMENT_RECORDED',
        ]);

        // =========================================================================
        // ÉTAPE 5 : Génération du Contrat VEFA avec PDF Binaire & SHA-256
        // =========================================================================
        $contractService = app(ContractService::class);
        $contract = $contractService->createContract($reservation, null, $this->commercial->id);

        $version = $contract->latestVersion;
        $this->assertNotNull($version->pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($version->pdf_path));

        $pdfBytes = Storage::disk('local')->get($version->pdf_path);
        $this->assertStringStartsWith('%PDF-1.4', $pdfBytes);
        $this->assertEquals(hash('sha256', $pdfBytes), $version->checksum);

        // =========================================================================
        // ÉTAPE 6 : Constitution & Contrôle du Dossier KYC Contextuel
        // =========================================================================
        DocumentRequirement::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pièce d\'identité officielle',
            'document_type' => 'identity_card',
            'target_buyer_type' => 'individual',
            'is_mandatory' => true,
        ]);

        $docService = app(BuyerDocumentService::class);
        $cni = $docService->uploadDocument(
            contactId: $contact->id,
            documentType: 'identity_card',
            title: 'CNI M. Sylla',
            filePath: 'cni_bytes',
            reservationId: $reservation->id,
            tenantId: $this->tenant->id,
        );

        $this->assertEquals(0, $docService->getDossierCompletionPercentage($contact, $reservation));

        $docService->verifyDocument($cni, $this->director->id);
        $this->assertEquals(100.0, $docService->getDossierCompletionPercentage($contact, $reservation));

        // =========================================================================
        // ÉTAPE 7 : Revue, Approbation et Émission en Signature
        // =========================================================================
        $contractService->submitForReview($contract, $this->commercial->id);
        $contractService->approveContract($contract, $this->director->id);
        $contractService->sendForSignature($contract, null, $this->director->id);

        $this->assertEquals('sent', $contract->fresh()->status);
        $sigReqId = $contract->fresh()->signature_request_id;

        // =========================================================================
        // ÉTAPE 8 : Webhook de Signature Idempotent & Scellage Légal
        // =========================================================================
        $secret = 'test_signature_secret';
        config(['services.signature.yousign.webhook_secret' => $secret]);

        $webhookPayload = [
            'event_id' => 'EVT-INTEG-FINAL-001',
            'event_type' => 'contract.signed',
            'signature_request_id' => $sigReqId,
            'signed_pdf_url' => 'contracts/signed/CTR-FINAL-CERT.pdf',
        ];

        $rawBody = json_encode($webhookPayload);
        $hmac = hash_hmac('sha256', $rawBody, $secret);

        $webhookResponse = $this->postJson('/api/webhooks/signature/yousign', $webhookPayload, [
            'X-Signature-SHA256' => $hmac,
        ]);

        $webhookResponse->assertStatus(200);
        $webhookResponse->assertJson(['status' => 'processed']);

        // Contrat marqué signé et réservation confirmée automatiquement
        $this->assertEquals('signed', $contract->fresh()->status);
        $this->assertEquals('confirmed', $reservation->fresh()->status);

        // =========================================================================
        // ÉTAPE 9 : Solde Final du Dossier & Vente Achevée (Completed)
        // =========================================================================
        $finalPayment = $reservationService->recordPayment($reservation, [
            'amount' => 72000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'proof_reference' => 'VIR-SOLDE-FINAL-001',
        ]);

        $reservation->refresh();
        $this->unit->refresh();

        $this->assertEquals(0, $reservation->remaining_balance);
        $this->assertEquals('completed', $reservation->status);
        $this->assertEquals('sold', $this->unit->status);
    }

    #[Test]
    public function it_runs_health_check_endpoint_and_reports_healthy(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'healthy',
            'checks' => [
                'database' => ['status' => 'ok'],
                'storage' => ['status' => 'ok'],
                'queues' => ['status' => 'ok'],
                'scheduler' => ['status' => 'ok'],
            ],
        ]);
    }

    #[Test]
    public function it_executes_artisan_health_check_command_with_json_flag(): void
    {
        $this->artisan('crm:health-check --json')
            ->assertExitCode(0);
    }
}
