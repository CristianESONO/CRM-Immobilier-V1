<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Policies\ReservationPolicy;
use App\Services\RefundService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GovernanceAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenantData(string $slug = 'promoteur-gret'): array
    {
        $tenant = Tenant::create(['slug' => $slug, 'name' => ucfirst($slug)]);
        session(['tenant_id' => $tenant->id]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
            'email' => "admin@{$slug}.com",
            'is_active' => true,
        ]);

        $commercial = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'commercial',
            'email' => "commercial@{$slug}.com",
            'is_active' => true,
        ]);

        $observer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'observer',
            'email' => "observer@{$slug}.com",
            'is_active' => true,
        ]);

        $source = Source::create(['tenant_id' => $tenant->id, 'channel' => 'web', 'label' => 'Web']);
        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Ousmane',
            'last_name' => 'Sow',
            'phone_e164' => '+221775556677',
        ]);

        $property = Property::create([
            'tenant_id' => $tenant->id,
            'name' => 'Résidence Mermoz',
            'location' => 'Mermoz, Dakar',
            'property_type' => 'apartment',
        ]);

        $unit = Unit::create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
            'reference' => 'LOT-MRZ-101',
            'price' => 60000000,
            'status' => 'available',
        ]);

        $resService = new ReservationService();
        $reservation = $resService->createReservation([
            'tenant_id' => $tenant->id,
            'reference' => 'RES-MRZ-001',
            'contact_id' => $contact->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'assigned_to' => $commercial->id,
            'total_amount' => 60000000,
            'deposit_amount' => 3000000,
            'status' => 'option',
        ]);

        return compact('tenant', 'admin', 'commercial', 'observer', 'contact', 'property', 'unit', 'reservation', 'resService');
    }

    // =========================================================================
    // 1. RBAC & MULTI-TENANT AUTHORIZATION POLICIES
    // =========================================================================

    #[Test]
    public function it_strictly_blocks_cross_tenant_access_in_reservation_policy(): void
    {
        $tenantA = $this->setupTenantData('tenant-a');
        $tenantB = $this->setupTenantData('tenant-b');

        $policy = new ReservationPolicy();

        // Le commercial de Tenant A ne peut pas voir la réservation de Tenant B
        $this->assertFalse($policy->view($tenantA['commercial'], $tenantB['reservation']));

        // Le commercial de Tenant A peut voir sa réservation sur son propre tenant
        $this->assertTrue($policy->view($tenantA['commercial'], $tenantA['reservation']));

        // Un super admin peut accéder à tout
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->assertTrue($policy->view($superAdmin, $tenantB['reservation']));
    }

    #[Test]
    public function it_restricts_observer_role_to_read_only_access(): void
    {
        $data = $this->setupTenantData('observer-test');
        $policy = new ReservationPolicy();
        $observer = $data['observer'];
        $reservation = $data['reservation'];

        // L'observateur peut voir
        $this->assertTrue($policy->viewAny($observer));
        $this->assertTrue($policy->view($observer, $reservation));

        // Mais NE PEUT PAS créer, modifier, encaisser ou annuler
        $this->assertFalse($policy->create($observer));
        $this->assertFalse($policy->update($observer, $reservation));
        $this->assertFalse($policy->recordPayment($observer, $reservation));
        $this->assertFalse($policy->cancel($observer, $reservation));
        $this->assertFalse($policy->delete($observer, $reservation));
    }

    #[Test]
    public function it_allows_assigned_commercial_and_admin_to_manage_reservation(): void
    {
        $data = $this->setupTenantData('commercial-perms');
        $policy = new ReservationPolicy();
        $commercial = $data['commercial'];
        $admin = $data['admin'];
        $reservation = $data['reservation'];

        // Le commercial assigné peut modifier et encaisser
        $this->assertTrue($policy->update($commercial, $reservation));
        $this->assertTrue($policy->recordPayment($commercial, $reservation));

        // Mais ne peut pas supprimer
        $this->assertFalse($policy->delete($commercial, $reservation));

        // L'admin peut tout administrer sur son tenant
        $this->assertTrue($policy->update($admin, $reservation));
        $this->assertTrue($policy->delete($admin, $reservation));
    }

    // =========================================================================
    // 2. JOURNAL D'AUDIT CENTRAL IMMUABLE (AUDITLOG)
    // =========================================================================

    #[Test]
    public function it_logs_immutable_audit_entries_for_business_operations(): void
    {
        $data = $this->setupTenantData('audit-check');
        $reservation = $data['reservation'];
        $resService = $data['resService'];
        $admin = $data['admin'];

        $this->actingAs($admin);

        // 1. Audit lors de la création de la réservation
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $data['tenant']->id,
            'action' => 'RESERVATION_CREATED',
            'auditable_type' => Reservation::class,
            'auditable_id' => $reservation->id,
        ]);

        // 2. Enregistrement d'un paiement
        $payment = $resService->recordPayment($reservation, [
            'amount' => 3000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'proof_reference' => 'VIR-AUDIT-001',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $data['tenant']->id,
            'action' => 'PAYMENT_RECORDED',
            'auditable_type' => Payment::class,
            'auditable_id' => $payment->id,
        ]);

        // 3. Annulation
        $resService->cancelReservation($reservation->fresh(), 'Désistement client');

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $data['tenant']->id,
            'action' => 'RESERVATION_CANCELLED',
            'auditable_type' => Reservation::class,
            'auditable_id' => $reservation->id,
        ]);
    }

    // =========================================================================
    // 3. CYCLE DE VIE STRUCTURÉ DES REMBOURSEMENTS (REFUNDS)
    // =========================================================================

    #[Test]
    public function it_automatically_initiates_pending_refund_when_cancelling_with_collected_funds(): void
    {
        $data = $this->setupTenantData('refund-auto');
        $reservation = $data['reservation'];
        $resService = $data['resService'];

        // Encaissement de 3 000 000 FCFA
        $resService->recordPayment($reservation, [
            'amount' => 3000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]);

        // Annulation de la réservation
        $resService->cancelReservation($reservation->fresh(), 'Rétractation délai de réflexion');

        // Un objet Refund au statut pending doit avoir été créé automatiquement
        $this->assertDatabaseHas('refunds', [
            'tenant_id' => $data['tenant']->id,
            'reservation_id' => $reservation->id,
            'amount' => 3000000,
            'status' => 'pending',
        ]);

        // Vérification de l'audit
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $data['tenant']->id,
            'action' => 'REFUND_REQUESTED',
        ]);
    }

    #[Test]
    public function it_executes_complete_refund_approval_and_completion_workflow(): void
    {
        $data = $this->setupTenantData('refund-workflow');
        $reservation = $data['reservation'];
        $admin = $data['admin'];

        // Encaissement préalable de 5 000 000 FCFA
        $data['resService']->recordPayment($reservation, [
            'amount' => 5000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]);

        $refundService = new RefundService();

        // 1. Création d'une demande de remboursement manuelle
        $refund = $refundService->requestRefund(
            reservation: $reservation->fresh(),
            amount: 2000000,
            reason: 'Trop-perçu sur appel de fonds',
            userId: $admin->id
        );

        $this->assertEquals('pending', $refund->status);

        // 2. Approbation par l'administrateur
        $approvedRefund = $refundService->approveRefund($refund, $admin->id);
        $this->assertEquals('approved', $approvedRefund->status);
        $this->assertEquals($admin->id, $approvedRefund->approved_by_user_id);
        $this->assertNotNull($approvedRefund->approved_at);

        // 3. Exécution effective du virement bancaire
        $completedRefund = $refundService->processRefund($approvedRefund, [
            'payment_method' => 'bank_transfer',
            'proof_reference' => 'VIR-SGBS-REMB-2026-99',
            'notes' => 'Règlement validé par le DAF',
        ], $admin->id);

        $this->assertEquals('completed', $completedRefund->status);
        $this->assertEquals($admin->id, $completedRefund->processed_by_user_id);
        $this->assertNotNull($completedRefund->processed_at);
        $this->assertEquals('VIR-SGBS-REMB-2026-99', $completedRefund->proof_reference);
    }

    #[Test]
    public function it_handles_refund_rejection_with_reason(): void
    {
        $data = $this->setupTenantData('refund-reject');
        $reservation = $data['reservation'];
        $admin = $data['admin'];

        // Encaissement préalable de 2 000 000 FCFA
        $data['resService']->recordPayment($reservation, [
            'amount' => 2000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]);

        $refundService = new RefundService();
        $refund = $refundService->requestRefund($reservation->fresh(), 1000000, 'Demande injustifiée', $admin->id);

        $rejected = $refundService->rejectRefund($refund, 'Paiement non confirmé en banque', $admin->id);

        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals('Paiement non confirmé en banque', $rejected->rejection_reason);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'REFUND_REJECTED',
            'auditable_id' => $refund->id,
        ]);
    }
}
