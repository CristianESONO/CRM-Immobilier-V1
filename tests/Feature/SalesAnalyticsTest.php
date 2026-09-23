<?php

namespace Tests\Feature;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\Filament\Widgets\ConversionWidget;
use App\Filament\Widgets\SalesFunnelWidget;
use App\Filament\Widgets\SalesOverviewWidget;
use App\Filament\Widgets\SalesVelocityWidget;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Analytics\SalesAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesAnalyticsTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        // Tenant A setup
        $this->tenantA = Tenant::create(['name' => 'Promotion Azur', 'slug' => 'azur']);
        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Directeur Azur',
            'email' => 'directeur@azur.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->sourceA = Source::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'website',
            'label' => 'Site Web Azur',
            'is_active' => true,
        ]);
        $this->propertyA = Property::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Résidence Azur 1',
            'city' => 'Dakar',
            'status' => 'available',
        ]);

        // Tenant B setup
        $this->tenantB = Tenant::create(['name' => 'Promotion Sahel', 'slug' => 'sahel']);
        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Directeur Sahel',
            'email' => 'directeur@sahel.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->sourceB = Source::create([
            'tenant_id' => $this->tenantB->id,
            'channel' => 'ad_campaign',
            'label' => 'Campagne Sahel',
            'is_active' => true,
        ]);
        $this->propertyB = Property::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Résidence Sahel 1',
            'city' => 'Abidjan',
            'status' => 'available',
        ]);
    }

    #[Test]
    public function it_strictly_isolates_analytics_between_tenants()
    {
        // 1. Seed Tenant A: 10 contacts (4 qualified), 5 opps, 2 reservations, 1 payment, 1 signed contract
        $unitsA = [];
        for ($i = 1; $i <= 3; $i++) {
            $unitsA[] = Unit::create([
                'tenant_id' => $this->tenantA->id,
                'property_id' => $this->propertyA->id,
                'reference' => "LOT-A-{$i}",
                'area' => 85.0,
                'price' => 50000000,
                'status' => 'available',
            ]);
        }

        $contactsA = [];
        for ($i = 1; $i <= 10; $i++) {
            $contactsA[] = Contact::create([
                'tenant_id' => $this->tenantA->id,
                'source_id' => $this->sourceA->id,
                'assigned_to' => $this->userA->id,
                'first_name' => "ProspectA_{$i}",
                'last_name' => 'Test',
                'email' => "prospect_a_{$i}@test.com",
                'phone' => "+2217700000{$i}",
                'status' => $i <= 4 ? 'qualifie' : 'nouveau',
                'q_replied_at' => $i <= 4 ? now() : null,
                'q_project_at' => $i <= 4 ? now() : null,
                'q_budget_at' => $i <= 4 ? now() : null,
                'q_source_at' => $i <= 4 ? now() : null,
                'qualified_at' => $i <= 4 ? now() : null,
                'first_response_minutes' => 60,
            ]);
        }

        for ($i = 1; $i <= 5; $i++) {
            Opportunity::create([
                'tenant_id' => $this->tenantA->id,
                'contact_id' => $contactsA[$i - 1]->id,
                'property_id' => $this->propertyA->id,
                'unit_id' => $unitsA[0]->id,
                'assigned_to' => $this->userA->id,
                'title' => "Projet A-{$i}",
                'stage' => $i <= 2 ? 'visite_realisee' : ($i === 3 ? 'offre_en_cours' : 'decouverte'),
                'amount' => 50000000,
            ]);
        }

        $resaA1 = Reservation::create([
            'tenant_id' => $this->tenantA->id,
            'reference' => 'RES-A-001',
            'contact_id' => $contactsA[0]->id,
            'property_id' => $this->propertyA->id,
            'unit_id' => $unitsA[0]->id,
            'assigned_to' => $this->userA->id,
            'status' => 'confirmed',
            'total_amount' => 50000000,
            'deposit_amount' => 10000000,
            'reserved_at' => now(),
        ]);

        $resaA2 = Reservation::create([
            'tenant_id' => $this->tenantA->id,
            'reference' => 'RES-A-002',
            'contact_id' => $contactsA[1]->id,
            'property_id' => $this->propertyA->id,
            'unit_id' => $unitsA[1]->id,
            'assigned_to' => $this->userA->id,
            'status' => 'option',
            'total_amount' => 50000000,
            'deposit_amount' => 10000000,
            'reserved_at' => now(),
        ]);

        Payment::create([
            'tenant_id' => $this->tenantA->id,
            'reference' => 'PAY-A-001',
            'reservation_id' => $resaA1->id,
            'amount' => 20000000,
            'status' => 'validated',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'proof_reference' => 'PROOF-A-001',
        ]);

        Contract::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $resaA1->id,
            'contract_number' => 'CTR-A-001',
            'title' => 'Contrat de Réservation Azur',
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        // 2. Seed Tenant B: 100 contacts, 40 opps, 20 reservations, 500M collected
        for ($i = 1; $i <= 100; $i++) {
            Contact::create([
                'tenant_id' => $this->tenantB->id,
                'source_id' => $this->sourceB->id,
                'assigned_to' => $this->userB->id,
                'first_name' => "ProspectB_{$i}",
                'last_name' => 'Test',
                'email' => "prospect_b_{$i}@test.com",
                'phone' => "+2250700000{$i}",
                'status' => 'nouveau',
            ]);
        }

        // Test as Tenant A
        $this->actingAs($this->userA);
        /** @var SalesAnalyticsService $service */
        $service = app(SalesAnalyticsService::class);
        $overviewA = $service->getOverview();

        $this->assertEquals(10, $overviewA->totalProspects, 'Tenant A must see exactly 10 contacts');
        $this->assertEquals(4, $overviewA->qualifiedProspects, 'Tenant A must see exactly 4 qualified contacts');
        $this->assertEquals(5, $overviewA->totalOpportunities, 'Tenant A must see exactly 5 opportunities');
        $this->assertEquals(2, $overviewA->totalReservations, 'Tenant A must see exactly 2 reservations');
        $this->assertEquals(1, $overviewA->totalSignedContracts, 'Tenant A must see exactly 1 signed contract');
        $this->assertEquals(100000000.0, $overviewA->reservedRevenue, 'CA réservé A must be 100M');
        $this->assertEquals(20000000.0, $overviewA->collectedRevenue, 'CA encaissé A must be 20M');
        $this->assertEquals(80000000.0, $overviewA->remainingRevenue, 'CA restant A must be 80M');
        $this->assertEquals(50000000.0, $overviewA->averageBasket, 'Panier moyen A must be 50M');
        $this->assertEquals(20.0, $overviewA->collectionRate, 'Taux encaissement A must be 20%');
        $this->assertEquals(40.0, $overviewA->qualificationRate, 'Taux qualification A must be 40%');

        // Test as Tenant B
        $this->actingAs($this->userB);
        $overviewB = $service->getOverview();

        $this->assertEquals(100, $overviewB->totalProspects, 'Tenant B must see exactly 100 contacts');
        $this->assertEquals(0, $overviewB->totalReservations, 'Tenant B must have 0 reservations');
        $this->assertEquals(0.0, $overviewB->reservedRevenue, 'Tenant B must have 0 CA réservé');
    }

    #[Test]
    public function it_handles_arithmetic_safety_on_empty_dataset()
    {
        $this->actingAs($this->userA);
        /** @var SalesAnalyticsService $service */
        $service = app(SalesAnalyticsService::class);

        $overview = $service->getOverview();
        $funnel = $service->getFunnel();
        $conversions = $service->getConversionMetrics();

        $this->assertEquals(0, $overview->totalProspects);
        $this->assertEquals(0.0, $overview->qualificationRate);
        $this->assertEquals(0.0, $overview->opportunityToReservationRate);
        $this->assertEquals(0.0, $overview->reservationToSignatureRate);
        $this->assertEquals(0.0, $overview->globalConversionRate);
        $this->assertEquals(0.0, $overview->averageBasket);
        $this->assertEquals(0.0, $overview->collectionRate);

        $this->assertCount(7, $funnel->steps);
        $this->assertEquals(0.0, $funnel->overallConversionRate);
        $this->assertEquals(0.0, $conversions['global_lead_to_sale']);
    }

    #[Test]
    public function it_filters_kpis_by_time_period_and_attributes()
    {
        $this->actingAs($this->userA);

        // Contact created 45 days ago
        $oldContact = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $this->sourceA->id,
            'first_name' => 'Old',
            'last_name' => 'Contact',
            'email' => 'old@test.com',
        ]);
        Contact::where('id', $oldContact->id)->update(['created_at' => now()->subDays(45)]);

        // Contact created 5 days ago
        $recentContact = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $this->sourceA->id,
            'first_name' => 'Recent',
            'last_name' => 'Contact',
            'email' => 'recent@test.com',
        ]);
        Contact::where('id', $recentContact->id)->update(['created_at' => now()->subDays(5)]);

        /** @var SalesAnalyticsService $service */
        $service = app(SalesAnalyticsService::class);

        // All periods
        $filterAll = new AnalyticsFilterData(period: 'all');
        $overviewAll = $service->getOverview($filterAll);
        $this->assertEquals(2, $overviewAll->totalProspects);

        // Filter 30 days
        $filter30 = AnalyticsFilterData::fromArray(['period' => '30_days']);
        $overview30 = $service->getOverview($filter30);
        $this->assertEquals(1, $overview30->totalProspects);

        // Filter 7 days
        $filter7 = AnalyticsFilterData::fromArray(['period' => '7_days']);
        $overview7 = $service->getOverview($filter7);
        $this->assertEquals(1, $overview7->totalProspects);
    }

    #[Test]
    public function it_renders_all_sales_intelligence_widgets_without_errors()
    {
        $this->actingAs($this->userA);

        // Seed minimal data
        Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $this->sourceA->id,
            'first_name' => 'Widget',
            'last_name' => 'Tester',
            'email' => 'widget@tester.com',
            'first_response_minutes' => 45,
            'q_replied_at' => now(),
            'q_project_at' => now(),
            'q_budget_at' => now(),
            'q_source_at' => now(),
            'qualified_at' => now(),
        ]);

        // Instantiate widgets
        $overviewWidget = new SalesOverviewWidget();
        $statsOverview = invade($overviewWidget)->getStats();
        $this->assertNotEmpty($statsOverview);
        $this->assertCount(6, $statsOverview);

        $funnelWidget = new SalesFunnelWidget();
        $chartData = invade($funnelWidget)->getData();
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertCount(7, $chartData['labels']);

        $conversionWidget = new ConversionWidget();
        $conversionStats = invade($conversionWidget)->getStats();
        $this->assertNotEmpty($conversionStats);
        $this->assertCount(6, $conversionStats);

        $velocityWidget = new SalesVelocityWidget();
        $velocityStats = invade($velocityWidget)->getStats();
        $this->assertNotEmpty($velocityStats);
        $this->assertCount(4, $velocityStats);
    }

    #[Test]
    public function it_computes_velocity_kpis_and_funnel_step_conversions()
    {
        $this->actingAs($this->userA);

        // Create contact with 2 days between created and qualified
        $contact = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $this->sourceA->id,
            'first_name' => 'Velocity',
            'last_name' => 'Lead',
            'email' => 'velocity@lead.com',
            'first_response_minutes' => 90,
            'q_replied_at' => now()->subDays(3),
            'q_project_at' => now()->subDays(3),
            'q_budget_at' => now()->subDays(3),
            'q_source_at' => now()->subDays(3),
            'qualified_at' => now()->subDays(3),
        ]);
        Contact::where('id', $contact->id)->update(['created_at' => now()->subDays(5)]);

        $unit = Unit::create([
            'tenant_id' => $this->tenantA->id,
            'property_id' => $this->propertyA->id,
            'reference' => 'LOT-VEL-1',
            'area' => 100,
            'price' => 75000000,
            'status' => 'available',
        ]);

        $opp = Opportunity::create([
            'tenant_id' => $this->tenantA->id,
            'contact_id' => $contact->id,
            'property_id' => $this->propertyA->id,
            'unit_id' => $unit->id,
            'title' => 'Opp Velocity',
            'stage' => 'visite_realisee',
            'amount' => 75000000,
        ]);

        $resa = Reservation::create([
            'tenant_id' => $this->tenantA->id,
            'reference' => 'RES-VEL-1',
            'contact_id' => $contact->id,
            'property_id' => $this->propertyA->id,
            'unit_id' => $unit->id,
            'status' => 'confirmed',
            'total_amount' => 75000000,
            'deposit_amount' => 15000000,
            'reserved_at' => now()->subDays(1),
        ]);
        Reservation::where('id', $resa->id)->update(['created_at' => now()->subDays(1)]);

        $contract = Contract::create([
            'tenant_id' => $this->tenantA->id,
            'reservation_id' => $resa->id,
            'contract_number' => 'CTR-VEL-1',
            'title' => 'Contrat Velocity',
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        /** @var SalesAnalyticsService $service */
        $service = app(SalesAnalyticsService::class);
        $velocities = $service->getVelocityMetrics();
        $funnel = $service->getFunnel();

        // 90 minutes / 60 = 1.5 hours
        $this->assertEquals(1.5, $velocities['average_first_response_hours']);
        // 5 days ago to 3 days ago = 2.0 days
        $this->assertEquals(2.0, $velocities['average_lead_to_qualified_days']);
        // 3 days ago to 1 day ago = 2.0 days
        $this->assertEquals(2.0, $velocities['average_qualified_to_reservation_days']);
        // 1 day ago to now = 1.0 day
        $this->assertEquals(1.0, $velocities['average_reservation_to_signed_days']);

        // Check funnel steps
        $this->assertCount(7, $funnel->steps);
        $this->assertEquals('prospects', $funnel->steps[0]->key);
        $this->assertEquals(1, $funnel->steps[0]->count);
        $this->assertEquals(100.0, $funnel->steps[0]->stepConversionRate);

        $this->assertEquals('signatures', $funnel->steps[6]->key);
        $this->assertEquals(1, $funnel->steps[6]->count);
        $this->assertEquals(100.0, $funnel->steps[6]->globalConversionRate);
        $this->assertNotNull($funnel->steps[6]->drillDownUrl);
    }
}
