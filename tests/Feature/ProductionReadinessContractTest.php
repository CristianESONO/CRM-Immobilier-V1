<?php

namespace Tests\Feature;

use App\Models\BuyerDocument;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractVersion;
use App\Models\DocumentRequirement;
use App\Models\PaymentSchedule;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\SignatureWebhookEvent;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Documents\BuyerDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductionReadinessContractTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $commercialA;
    private User $adminB;
    private Property $propertyA;
    private Unit $unitA;
    private Contact $contactA;
    private Reservation $reservationA;
    private Contract $contractA;
    private ContractVersion $versionA;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tenantA = Tenant::create(['name' => 'Promotion Atlantique', 'slug' => 'atlantique']);
        $this->tenantB = Tenant::create(['name' => 'Promotion Equateur', 'slug' => 'equateur']);

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Directeur Juridique Atlantique',
            'email' => 'juridique@atlantique.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->commercialA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Commercial Atlantique',
            'email' => 'commercial@atlantique.com',
            'password' => bcrypt('secret'),
            'role' => 'commercial',
            'is_active' => true,
        ]);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin Equateur',
            'email' => 'admin@equateur.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $source = Source::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'web',
            'label' => 'Campagne Digitale',
        ]);

        $this->propertyA = Property::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Résidence La Corniche',
            'location' => 'Dakar Almadies',
            'property_type' => 'apartment',
            'status' => 'available',
        ]);

        $this->unitA = Unit::create([
            'tenant_id' => $this->tenantA->id,
            'property_id' => $this->propertyA->id,
            'reference' => 'LOT-C204',
            'area' => 110.0,
            'price' => 75000000,
            'status' => 'reserved',
        ]);

        $this->contactA = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $source->id,
            'first_name' => 'Seydou',
            'last_name' => 'Diop',
            'email' => 'seydou.diop@example.com',
            'phone_e164' => '+221770001122',
            'status' => 'client',
        ]);

        $this->reservationA = Reservation::create([
            'tenant_id' => $this->tenantA->id,
            'contact_id' => $this->contactA->id,
            'property_id' => $this->propertyA->id,
            'unit_id' => $this->unitA->id,
            'assigned_to' => $this->commercialA->id,
            'reference' => 'RES-2026-C204',
            'total_amount' => 75000000,
            'deposit_amount' => 7500000,
            'status' => 'option',
            'expires_at' => now()->addDays(15),
        ]);

        PaymentSchedule::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $this->reservationA->id,
            'stage_name' => 'reservation',
            'label' => 'Dépôt de garantie',
            'percentage' => 10,
            'expected_amount' => 7500000,
            'paid_amount' => 7500000,
            'status' => 'paid',
            'due_date' => now()->subDay(),
        ]);

        // Création du contrat initial avec PDF
        $contractService = app(ContractService::class);
        $this->contractA = $contractService->createContract($this->reservationA, null, $this->commercialA->id);
        $this->versionA = $this->contractA->versions()->first();
    }

    #[Test]
    public function it_generates_valid_binary_pdf_and_stores_on_private_disk_with_sha256(): void
    {
        $this->assertNotNull($this->versionA->pdf_path);
        $this->assertGreaterThan(0, $this->versionA->pdf_size);

        // Vérification de la présence sur le disque privé
        $this->assertTrue(Storage::disk('local')->exists($this->versionA->pdf_path));

        // Lecture du flux binaire PDF
        $pdfContent = Storage::disk('local')->get($this->versionA->pdf_path);

        // Validation de la conformité du standard PDF
        $this->assertStringStartsWith('%PDF-1.4', $pdfContent);
        $this->assertStringContainsString('%%EOF', $pdfContent);

        // Validation de l'empreinte SHA-256 calculée sur le fichier binaire archivé
        $expectedChecksum = hash('sha256', $pdfContent);
        $this->assertEquals($expectedChecksum, $this->versionA->checksum);
        $this->assertEquals(strlen($pdfContent), $this->versionA->pdf_size);
    }

    #[Test]
    public function it_allows_authorized_tenant_user_to_download_contract_pdf(): void
    {
        $response = $this->actingAs($this->commercialA)
            ->get(route('documents.contracts.download', ['contractVersion' => $this->versionA->id]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function it_blocks_cross_tenant_contract_download_with_403_forbidden(): void
    {
        // Utilisateur du Tenant B tente d'accéder au contrat du Tenant A
        $response = $this->actingAs($this->adminB)
            ->get(route('documents.contracts.download', ['contractVersion' => $this->versionA->id]));

        $response->assertStatus(403);
    }

    #[Test]
    public function it_allows_authorized_user_to_download_buyer_kyc_document(): void
    {
        $docService = app(BuyerDocumentService::class);
        $doc = $docService->uploadDocument(
            contactId: $this->contactA->id,
            documentType: 'identity_card',
            title: 'Passeport M. Diop',
            filePath: 'fake_passport_content_bytes',
            reservationId: $this->reservationA->id,
            tenantId: $this->tenantA->id,
        );

        $response = $this->actingAs($this->commercialA)
            ->get(route('documents.kyc.download', ['buyerDocument' => $doc->id]));

        $response->assertStatus(200);
    }

    #[Test]
    public function it_blocks_cross_tenant_kyc_download_with_403_forbidden(): void
    {
        $docService = app(BuyerDocumentService::class);
        $doc = $docService->uploadDocument(
            contactId: $this->contactA->id,
            documentType: 'identity_card',
            title: 'Passeport Confidentiel',
            filePath: 'private_bytes',
            reservationId: $this->reservationA->id,
            tenantId: $this->tenantA->id,
        );

        // Utilisateur du Tenant B strictement bloqué
        $response = $this->actingAs($this->adminB)
            ->get(route('documents.kyc.download', ['buyerDocument' => $doc->id]));

        $response->assertStatus(403);
    }

    #[Test]
    public function it_processes_signature_webhook_and_updates_contract_to_signed(): void
    {
        $contractService = app(ContractService::class);
        $contractService->submitForReview($this->contractA, $this->adminA->id);
        $contractService->approveContract($this->contractA, $this->adminA->id);
        $contractService->sendForSignature($this->contractA, null, $this->adminA->id);

        $this->assertEquals('sent', $this->contractA->fresh()->status);
        $envelopeId = $this->contractA->fresh()->signature_request_id;

        $secret = 'test_signature_secret';
        config(['services.signature.yousign.webhook_secret' => $secret]);

        $payload = [
            'event_id' => 'EVT-WEBHOOK-999',
            'event_type' => 'contract.signed',
            'signature_request_id' => $envelopeId,
            'signed_pdf_url' => 'tenants/1/contracts/signed_cert.pdf',
        ];

        $rawBody = json_encode($payload);
        $hmac = hash_hmac('sha256', $rawBody, $secret);

        $response = $this->postJson('/api/webhooks/signature/yousign', $payload, [
            'X-Signature-SHA256' => $hmac,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'processed']);

        // Vérification de la transition d'état et effet de bord
        $this->assertEquals('signed', $this->contractA->fresh()->status);
        $this->assertEquals('confirmed', $this->reservationA->fresh()->status);

        // Vérification de l'enregistrement de traçabilité webhook
        $this->assertDatabaseHas('signature_webhook_events', [
            'provider' => 'yousign',
            'event_id' => 'EVT-WEBHOOK-999',
            'status' => 'processed',
        ]);

        // Vérification de l'audit log
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenantA->id,
            'auditable_type' => Contract::class,
            'auditable_id' => $this->contractA->id,
            'action' => 'WEBHOOK_SIGNATURE_PROCESSED',
        ]);
    }

    #[Test]
    public function it_strictly_enforces_idempotency_on_signature_webhooks(): void
    {
        $contractService = app(ContractService::class);
        $contractService->submitForReview($this->contractA, $this->adminA->id);
        $contractService->approveContract($this->contractA, $this->adminA->id);
        $contractService->sendForSignature($this->contractA, null, $this->adminA->id);

        $envelopeId = $this->contractA->fresh()->signature_request_id;
        $secret = 'test_signature_secret';
        config(['services.signature.yousign.webhook_secret' => $secret]);

        $payload = [
            'event_id' => 'EVT-IDEMPOTENT-001',
            'event_type' => 'contract.signed',
            'signature_request_id' => $envelopeId,
        ];

        $rawBody = json_encode($payload);
        $hmac = hash_hmac('sha256', $rawBody, $secret);

        // Premier passage : Traitement normal
        $response1 = $this->postJson('/api/webhooks/signature/yousign', $payload, [
            'X-Signature-SHA256' => $hmac,
        ]);
        $response1->assertStatus(200);
        $response1->assertJson(['status' => 'processed']);

        // Second passage identique (Rejeu réseau) : Doit être détecté comme doublon et ignoré sans erreur
        $response2 = $this->postJson('/api/webhooks/signature/yousign', $payload, [
            'X-Signature-SHA256' => $hmac,
        ]);
        $response2->assertStatus(200);
        $response2->assertJson(['status' => 'already_processed']);

        // Strictement 1 seul enregistrement d'événement en base
        $this->assertEquals(1, SignatureWebhookEvent::where('event_id', 'EVT-IDEMPOTENT-001')->count());

        // Strictement 1 seul log d'audit
        $auditCount = \App\Models\AuditLog::where('action', 'WEBHOOK_SIGNATURE_PROCESSED')
            ->where('auditable_id', $this->contractA->id)
            ->count();
        $this->assertEquals(1, $auditCount);
    }

    #[Test]
    public function it_rejects_webhook_with_invalid_hmac_signature(): void
    {
        $secret = 'test_signature_secret';
        config(['services.signature.yousign.webhook_secret' => $secret]);

        $payload = [
            'event_id' => 'EVT-FAKE-001',
            'event_type' => 'contract.signed',
        ];

        $response = $this->postJson('/api/webhooks/signature/yousign', $payload, [
            'X-Signature-SHA256' => 'invalid_fake_hmac_signature',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Invalid webhook signature HMAC.']);
    }

    #[Test]
    public function it_dynamically_evaluates_contextual_kyc_requirements_for_individual_vs_company(): void
    {
        $docService = app(BuyerDocumentService::class);

        // Définition de la matrice des exigences du promoteur
        DocumentRequirement::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'CNI / Passeport officiel',
            'document_type' => 'identity_card',
            'target_buyer_type' => 'individual',
            'financing_type' => 'all',
            'residence_type' => 'all',
            'is_mandatory' => true,
        ]);

        DocumentRequirement::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Justificatif de domicile (< 3 mois)',
            'document_type' => 'proof_of_residence',
            'target_buyer_type' => 'individual',
            'financing_type' => 'all',
            'residence_type' => 'all',
            'is_mandatory' => true,
        ]);

        DocumentRequirement::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Statuts certifiés & Extrait RCCM',
            'document_type' => 'company_statutes',
            'target_buyer_type' => 'company',
            'financing_type' => 'all',
            'residence_type' => 'all',
            'is_mandatory' => true,
        ]);

        // 1. Cas Particulier ($this->contactA n'a pas de company_name)
        $individualReqs = $docService->getApplicableRequirements($this->contactA);
        $this->assertCount(2, $individualReqs);
        $this->assertTrue($individualReqs->contains('document_type', 'identity_card'));
        $this->assertTrue($individualReqs->contains('document_type', 'proof_of_residence'));
        $this->assertFalse($individualReqs->contains('document_type', 'company_statutes'));

        // 2. Cas Société (contact avec company_name)
        $companyContact = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $this->contactA->source_id,
            'first_name' => 'Mamadou',
            'last_name' => 'Ba',
            'company_name' => 'Ba Investissements SARL',
            'email' => 'contact@bainvest.com',
            'status' => 'client',
        ]);

        $companyReqs = $docService->getApplicableRequirements($companyContact);
        $this->assertCount(1, $companyReqs);
        $this->assertTrue($companyReqs->contains('document_type', 'company_statutes'));

        // 3. Complétion KYC contextuelle
        // Avant upload : 0%
        $this->assertEquals(0, $docService->getDossierCompletionPercentage($companyContact));

        // Upload et validation des statuts
        $statutes = $docService->uploadDocument(
            contactId: $companyContact->id,
            documentType: 'company_statutes',
            title: 'Statuts Notariés SARL',
            filePath: 'statuts.pdf',
            tenantId: $this->tenantA->id,
        );
        $docService->verifyDocument($statutes, $this->adminA->id);

        // Dès que la seule pièce obligatoire société est validée -> 100% de complétion !
        $this->assertEquals(100.0, $docService->getDossierCompletionPercentage($companyContact));
    }
}
