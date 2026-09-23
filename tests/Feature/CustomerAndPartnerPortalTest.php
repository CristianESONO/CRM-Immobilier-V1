<?php

namespace Tests\Feature;

use App\Models\BuyerPortalAccess;
use App\Models\Contact;
use App\Models\PartnerPortalAccess;
use App\Models\Property;
use App\Models\Referrer;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerAndPartnerPortalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Source $source;
    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-20')->startOfDay());

        $this->tenant = Tenant::create(['name' => 'Promotion Portails', 'slug' => 'portals']);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Portails',
            'email' => 'admin@portals.test',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->source = Source::create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'web',
            'label' => 'Web Portails',
        ]);
        $this->property = Property::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Résidence Oasis',
            'location' => 'Saly',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function buyer_portal_login_and_dashboard_retrieval(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'first_name' => 'Fatou',
            'last_name' => 'Diop',
            'email' => 'fatou@diop.sn',
        ]);

        BuyerPortalAccess::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'email' => 'fatou@diop.sn',
            'password_hash' => bcrypt('secret123'),
        ]);

        // Login
        $response = $this->postJson('/portal/buyer/login', [
            'email' => 'fatou@diop.sn',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        $token = $response->json('portal_token');
        $this->assertNotEmpty($token);

        // Access Dashboard
        $dashResponse = $this->withHeaders(['X-Buyer-Portal-Token' => $token])
            ->getJson('/portal/buyer/dashboard');

        $dashResponse->assertStatus(200);
        $dashResponse->assertJsonPath('contact.first_name', 'Fatou');
    }

    #[Test]
    public function partner_portal_login_lead_submission_and_commission_calculation(): void
    {
        $referrer = Referrer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Agence Immobilière Teranga',
            'type' => 'agency',
        ]);

        PartnerPortalAccess::create([
            'tenant_id' => $this->tenant->id,
            'referrer_id' => $referrer->id,
            'email' => 'agence@teranga.sn',
            'password_hash' => bcrypt('partner123'),
            'commission_rate' => 3.00,
        ]);

        // Login
        $loginRes = $this->postJson('/portal/partner/login', [
            'email' => 'agence@teranga.sn',
            'password' => 'partner123',
        ]);
        $loginRes->assertStatus(200);
        $token = $loginRes->json('portal_token');

        // Submit Lead
        $submitRes = $this->withHeaders(['X-Partner-Portal-Token' => $token])
            ->postJson('/portal/partner/leads', [
                'first_name' => 'Ousmane',
                'last_name' => 'Ndiaye',
                'email' => 'ousmane@ndiaye.sn',
                'phone' => '+221770001122',
            ]);
        $submitRes->assertStatus(201);
        $contactId = $submitRes->json('contact_id');

        // Simuler une réservation pour ce lead
        $unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->property->id,
            'reference' => 'LOT-P-01',
            'area' => 100,
            'price' => 50_000_000,
            'status' => 'reserved',
        ]);

        Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reference' => 'RES-P-01',
            'contact_id' => $contactId,
            'property_id' => $this->property->id,
            'unit_id' => $unit->id,
            'assigned_to' => $this->user->id,
            'status' => 'confirmed',
            'total_amount' => 50_000_000,
            'deposit_amount' => 0,
            'reserved_at' => Carbon::now(),
        ]);

        // Access Dashboard
        $dashRes = $this->withHeaders(['X-Partner-Portal-Token' => $token])
            ->getJson('/portal/partner/dashboard');

        $dashRes->assertStatus(200);
        $this->assertEquals(50_000_000.0, $dashRes->json('total_sales_amount'));
        $this->assertEquals(1_500_000.0, $dashRes->json('commission_earned')); // 3% de 50M
    }
}
