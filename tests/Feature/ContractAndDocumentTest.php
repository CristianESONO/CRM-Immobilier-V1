<?php

namespace Tests\Feature;

use App\Exceptions\StateTransitionException;
use App\Models\AuditLog;
use App\Models\BuyerDocument;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractVersion;
use App\Models\PaymentSchedule;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Policies\ContractPolicy;
use App\Services\Contracts\ContractService;
use App\Services\Documents\BuyerDocumentService;
use App\Services\Signature\MockSignatureProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractAndDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $commercialA;
    private User $observerA;
    private User $adminB;
    private Property $propertyA;
    private Unit $unitA;
    private Contact $contactA;
    private Reservation $reservationA;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tenantA = Tenant::create(['name' => 'Promotion Azur', 'slug' => 'azur']);
        $this->tenantB = Tenant::create(['name' => 'Promotion Sahel', 'slug' => 'sahel']);

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin Azur',
            'email' => 'admin@azur.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->commercialA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Commercial Azur',
            'email' => 'commercial@azur.com',
            'password' => bcrypt('secret'),
            'role' => 'commercial',
            'is_active' => true,
        ]);

        $this->observerA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Auditeur Azur',
            'email' => 'observer@azur.com',
            'password' => bcrypt('secret'),
            'role' => 'observer',
            'is_active' => true,
        ]);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin Sahel',
            'email' => 'admin@sahel.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $source = Source::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'web',
            'label' => 'Site Web Promotion',
        ]);

        $this->propertyA = Property::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Résidence Les Palmiers',
            'location' => 'Abidjan Cocody',
            'property_type' => 'apartment',
            'status' => 'available',
        ]);

        $this->unitA = Unit::create([
            'tenant_id' => $this->tenantA->id,
            'property_id' => $this->propertyA->id,
            'reference' => 'LOT-A101',
            'area' => 85.5,
            'price' => 50000000,
            'status' => 'reserved',
        ]);

        $this->contactA = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $source->id,
            'first_name' => 'Amadou',
            'last_name' => 'Kone',
            'email' => 'amadou.kone@example.com',
            'phone_e164' => '+22507000001',
            'status' => 'client',
        ]);

        $this->reservationA = Reservation::create([
            'tenant_id' => $this->tenantA->id,
            'contact_id' => $this->contactA->id,
            'property_id' => $this->propertyA->id,
            'unit_id' => $this->unitA->id,
            'assigned_to' => $this->commercialA->id,
            'reference' => 'RES-2026-001',
            'total_amount' => 50000000,
            'deposit_amount' => 5000000,
            'status' => 'option',
            'expires_at' => now()->addDays(15),
        ]);

        // Échéancier VEFA
        PaymentSchedule::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $this->reservationA->id,
            'stage_name' => 'reservation',
            'label' => 'Dépôt de garantie réservation',
            'percentage' => 10,
            'expected_amount' => 5000000,
            'paid_amount' => 5000000,
            'status' => 'paid',
            'due_date' => now()->subDays(2),
        ]);

        PaymentSchedule::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $this->reservationA->id,
            'stage_name' => 'foundations',
            'label' => 'Achèvement des fondations',
            'percentage' => 25,
            'expected_amount' => 12500000,
            'paid_amount' => 0,
            'status' => 'pending',
            'due_date' => now()->addMonths(2),
        ]);
    }

    #[Test]
    public function it_generates_contract_from_reservation_with_default_template_and_computes_sha256(): void
    {
        $this->actingAs($this->commercialA);

        $template = ContractTemplate::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Contrat Réservation VEFA Test',
            'contract_type' => 'reservation_vefa',
            'content' => "CONTRAT VEFA #{{contract.number}}\nAcquéreur: {{buyer.full_name}} ({{buyer.email}})\nLot: {{unit.reference}} - Prix: {{financials.total_amount_formatted}}\n\nÉchéancier:\n{{payment_schedule_table}}",
            'is_active' => true,
        ]);

        $service = app(ContractService::class);
        $contract = $service->createContract($this->reservationA, $template->id, $this->commercialA->id);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $this->reservationA->id,
            'status' => 'generated',
            'current_version' => 1,
        ]);

        $this->assertCount(1, $contract->versions);
        $v1 = $contract->versions->first();

        $this->assertEquals(1, $v1->version_number);
        $this->assertStringContainsString('Amadou Kone', $v1->rendered_content);
        $this->assertStringContainsString('LOT-A101', $v1->rendered_content);
        $this->assertStringContainsString('50 000 000 FCFA', $v1->rendered_content);
        $this->assertStringContainsString('Achèvement des fondations', $v1->rendered_content);

        // Intégrité cryptographique SHA-256 sur le PDF archivé
        $this->assertNotNull($v1->pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($v1->pdf_path));
        $expectedChecksum = hash('sha256', Storage::disk('local')->get($v1->pdf_path));
        $this->assertEquals($expectedChecksum, $v1->checksum);

        // Traçabilité Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenantA->id,
            'auditable_type' => Contract::class,
            'auditable_id' => $contract->id,
            'action' => 'CONTRACT_CREATED',
        ]);
    }

    #[Test]
    public function it_guarantees_snapshot_immutability_even_if_crm_data_changes_later(): void
    {
        $this->actingAs($this->adminA);

        $service = app(ContractService::class);
        $contract = $service->createContract($this->reservationA, null, $this->adminA->id);

        $v1 = $contract->versions()->first();
        $originalChecksum = $v1->checksum;
        $originalSnapshotBuyer = $v1->snapshot_data['buyer']['full_name'];

        $this->assertEquals('Amadou Kone', $originalSnapshotBuyer);

        // Modification ultérieure des fiches CRM
        $this->contactA->update([
            'first_name' => 'Moussa',
            'last_name' => 'Traore',
            'email' => 'moussa.traore@example.com',
        ]);
        $this->unitA->update(['price' => 60000000]);

        // Rafraîchissement de la version
        $v1->refresh();

        // Le snapshot et l'empreinte demeurent strictement invariants
        $this->assertEquals($originalChecksum, $v1->checksum);
        $this->assertEquals('Amadou Kone', $v1->snapshot_data['buyer']['full_name']);
        $this->assertStringContainsString('Amadou Kone', $v1->rendered_content);
        $this->assertStringNotContainsString('Moussa Traore', $v1->rendered_content);
    }

    #[Test]
    public function it_regenerates_new_contract_version_with_incremented_version_number_and_reason(): void
    {
        $this->actingAs($this->commercialA);

        $service = app(ContractService::class);
        $contract = $service->createContract($this->reservationA, null, $this->commercialA->id);
        $this->assertEquals(1, $contract->current_version);

        // Génération d'une v2 suite à un avenant
        $v2 = $service->generateVersion($contract, 'Avenant modification finitions carrelage', null, $this->commercialA->id);

        $contract->refresh();
        $this->assertEquals(2, $contract->current_version);
        $this->assertEquals(2, $v2->version_number);
        $this->assertEquals('Avenant modification finitions carrelage', $v2->change_reason);
        $this->assertCount(2, $contract->versions);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenantA->id,
            'auditable_type' => Contract::class,
            'auditable_id' => $contract->id,
            'action' => 'CONTRACT_VERSION_GENERATED',
        ]);
    }

    #[Test]
    public function it_enforces_contract_state_machine_and_rejects_unauthorized_transitions(): void
    {
        $this->actingAs($this->adminA);

        $service = app(ContractService::class);
        $contract = $service->createContract($this->reservationA, null, $this->adminA->id);

        $this->assertEquals('generated', $contract->status);

        // Impossible de passer directement de generated à signed sans review et approbation
        $this->expectException(StateTransitionException::class);
        $service->recordSignedContract($contract, null, $this->adminA->id);
    }

    #[Test]
    public function it_executes_complete_contract_lifecycle_from_generated_to_signed(): void
    {
        $this->actingAs($this->adminA);

        $service = app(ContractService::class);
        $contract = $service->createContract($this->reservationA, null, $this->adminA->id);

        // 1. Soumission à la revue de conformité
        $service->submitForReview($contract, $this->adminA->id);
        $this->assertEquals('pending_review', $contract->fresh()->status);

        // 2. Approbation par la direction
        $service->approveContract($contract, $this->adminA->id);
        $this->assertEquals('approved', $contract->fresh()->status);
        $this->assertNotNull($contract->fresh()->approved_at);

        // 3. Envoi en signature électronique
        $service->sendForSignature($contract, null, $this->adminA->id);
        $this->assertEquals('sent', $contract->fresh()->status);
        $this->assertNotNull($contract->fresh()->signature_request_id);

        // 4. Réception du contrat signé certifié
        $service->recordSignedContract($contract, 'contracts/signed/CTR-2026.pdf', $this->adminA->id);

        $this->assertEquals('signed', $contract->fresh()->status);
        $this->assertNotNull($contract->fresh()->signed_at);

        // Effet de bord métier : la réservation en option est automatiquement confirmée
        $this->assertEquals('confirmed', $this->reservationA->fresh()->status);
    }

    #[Test]
    public function it_enforces_tenant_isolation_on_contracts(): void
    {
        $this->actingAs($this->commercialA);
        $service = app(ContractService::class);
        $contractA = $service->createContract($this->reservationA, null, $this->commercialA->id);

        $policy = new ContractPolicy();

        // Utilisateur du Tenant A autorisé
        $this->assertTrue($policy->view($this->commercialA, $contractA));

        // Utilisateur du Tenant B STRICTEMENT bloqué
        $this->assertFalse($policy->view($this->adminB, $contractA));
        $this->assertFalse($policy->update($this->adminB, $contractA));
        $this->assertFalse($policy->approve($this->adminB, $contractA));
    }

    #[Test]
    public function it_enforces_rbac_rules_on_contracts(): void
    {
        $this->actingAs($this->commercialA);
        $service = app(ContractService::class);
        $contract = $service->createContract($this->reservationA, null, $this->commercialA->id);

        $policy = new ContractPolicy();

        // Commercial peut consulter et éditer
        $this->assertTrue($policy->view($this->commercialA, $contract));
        $this->assertTrue($policy->update($this->commercialA, $contract));

        // Commercial NE PEUT PAS approuver (réservé Admin)
        $this->assertFalse($policy->approve($this->commercialA, $contract));

        // Admin PEUT approuver
        $this->assertTrue($policy->approve($this->adminA, $contract));

        // Observateur / Auditeur en lecture seule
        $this->assertFalse($policy->create($this->observerA));
        $this->assertFalse($policy->approve($this->observerA, $contract));
    }

    #[Test]
    public function it_manages_buyer_kyc_documents_and_calculates_completion_rate(): void
    {
        $this->actingAs($this->commercialA);
        $docService = app(BuyerDocumentService::class);

        // Complétion initiale : 0%
        $initialRate = $docService->getDossierCompletionPercentage($this->contactA);
        $this->assertEquals(0, $initialRate);

        // Dépôt pièce d'identité
        $cni = $docService->uploadDocument(
            contactId: $this->contactA->id,
            documentType: 'identity_card',
            title: 'CNI M. Kone',
            filePath: 'buyer_documents/cni_kone.pdf',
            reservationId: $this->reservationA->id,
            tenantId: $this->tenantA->id,
        );

        // Dépôt justificatif de domicile
        $justif = $docService->uploadDocument(
            contactId: $this->contactA->id,
            documentType: 'proof_of_residence',
            title: 'Facture CIE',
            filePath: 'buyer_documents/facture_cie.pdf',
            reservationId: $this->reservationA->id,
            tenantId: $this->tenantA->id,
        );

        // Validation de la CNI par l'admin
        $docService->verifyDocument($cni, $this->adminA->id);
        $cni->refresh();
        $this->assertEquals('verified', $cni->status);
        $this->assertEquals($this->adminA->id, $cni->verified_by_user_id);
        $this->assertNotNull($cni->verified_at);

        // 1 type validé sur 3 requis = 33.3%
        $rateWithOneVerified = $docService->getDossierCompletionPercentage($this->contactA);
        $this->assertEquals(33.3, $rateWithOneVerified);

        // Rejet du justificatif avec motif obligatoire
        $docService->rejectDocument($justif, 'Facture de plus de 3 mois non conforme', $this->adminA->id);
        $justif->refresh();
        $this->assertEquals('rejected', $justif->status);
        $this->assertEquals('Facture de plus de 3 mois non conforme', $justif->rejection_reason);

        // Traçabilité des actions documentaires
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenantA->id,
            'auditable_type' => BuyerDocument::class,
            'auditable_id' => $cni->id,
            'action' => 'BUYER_DOCUMENT_VERIFIED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenantA->id,
            'auditable_type' => BuyerDocument::class,
            'auditable_id' => $justif->id,
            'action' => 'BUYER_DOCUMENT_REJECTED',
        ]);
    }

    #[Test]
    public function it_executes_electronic_signature_flow_via_mock_provider(): void
    {
        $mockProvider = new MockSignatureProvider();

        $contract = Contract::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $this->reservationA->id,
            'contract_number' => 'CTR-2026-TEST',
            'status' => 'approved',
            'current_version' => 1,
        ]);

        $version = ContractVersion::create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'version_number' => 1,
            'snapshot_data' => ['test' => true],
            'rendered_content' => 'Test content',
            'checksum' => hash('sha256', 'Test content'),
        ]);

        $sigReqId = $mockProvider->createSignatureRequest($version);

        $this->assertStringStartsWith('SIG_REQ_', $sigReqId);

        $status = $mockProvider->getSignatureStatus($sigReqId);
        $this->assertEquals('signed', $status);

        $downloadPath = $mockProvider->downloadSignedDocument($sigReqId);
        $this->assertStringContainsString($sigReqId, $downloadPath);
    }
}
