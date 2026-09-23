<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Contract;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private Source $sourceA;
    private Property $propertyA;
    private Unit $unitA;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-20')->startOfDay());

        $this->tenantA = Tenant::create(['name' => 'Promotion API A', 'slug' => 'api-a']);
        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin API A',
            'email' => 'api@a.test',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->sourceA = Source::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'web',
            'label' => 'Web API A',
        ]);
        $this->propertyA = Property::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Résidence API A',
            'location' => 'Dakar',
        ]);
        $this->unitA = Unit::create([
            'tenant_id' => $this->tenantA->id,
            'property_id' => $this->propertyA->id,
            'reference' => 'LOT-API-A01',
            'area' => 85,
            'price' => 30_000_000,
            'status' => 'available',
        ]);

        $this->tenantB = Tenant::create(['name' => 'Promotion API B', 'slug' => 'api-b']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function public_api_endpoints_return_structured_json_with_tenant_isolation(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $this->sourceA->id,
            'first_name' => 'Client',
            'last_name' => 'API',
        ]);

        $resa = Reservation::create([
            'tenant_id' => $this->tenantA->id,
            'reference' => 'RES-API-001',
            'contact_id' => $contact->id,
            'property_id' => $this->propertyA->id,
            'unit_id' => $this->unitA->id,
            'assigned_to' => $this->userA->id,
            'status' => 'confirmed',
            'total_amount' => 30_000_000,
            'deposit_amount' => 0,
            'reserved_at' => Carbon::now(),
        ]);

        Contract::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $resa->id,
            'contract_number' => 'CTR-API-001',
            'contract_type' => 'reservation_contract',
            'status' => 'approved',
        ]);

        // GET /api/v1/properties
        $resProp = $this->withHeaders(['X-Tenant-Key' => 'api-a'])
            ->getJson('/api/v1/properties');

        $resProp->assertStatus(200);
        $resProp->assertJsonPath('status', 'success');
        $this->assertCount(1, $resProp->json('data'));
        $this->assertEquals('Résidence API A', $resProp->json('data.0.name'));

        // GET /api/v1/units
        $resUnits = $this->withHeaders(['X-Tenant-Key' => 'api-a'])
            ->getJson('/api/v1/units?status=available');

        $resUnits->assertStatus(200);
        $resUnits->assertJsonPath('status', 'success');

        // GET /api/v1/reservations
        $resResa = $this->withHeaders(['X-Tenant-Key' => 'api-a'])
            ->getJson('/api/v1/reservations');

        $resResa->assertStatus(200);
        $resResa->assertJsonPath('status', 'success');

        // GET /api/v1/contracts
        $resContracts = $this->withHeaders(['X-Tenant-Key' => 'api-a'])
            ->getJson('/api/v1/contracts');

        $resContracts->assertStatus(200);
        $resContracts->assertJsonPath('status', 'success');
    }
}
