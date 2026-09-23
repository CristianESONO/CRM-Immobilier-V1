<?php

namespace Tests\Feature;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\Filament\Widgets\StockAgingWidget;
use App\Filament\Widgets\StockByProgramWidget;
use App\Filament\Widgets\StockByTypeWidget;
use App\Filament\Widgets\StockOverviewWidget;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Analytics\StockAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Property $propertyA;
    private Property $propertyB;

    protected function setUp(): void
    {
        parent::setUp();

        // Tenant A setup
        $this->tenantA = Tenant::create(['name' => 'Promotion Littoral', 'slug' => 'littoral']);
        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Directeur Littoral',
            'email' => 'directeur@littoral.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->propertyA = Property::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Résidence Marina',
            'city' => 'Dakar',
            'status' => 'available',
        ]);

        // Tenant B setup
        $this->tenantB = Tenant::create(['name' => 'Promotion Teranga', 'slug' => 'teranga']);
        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Directeur Teranga',
            'email' => 'directeur@teranga.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->propertyB = Property::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Résidence Teranga 1',
            'city' => 'Saly',
            'status' => 'available',
        ]);
    }

    #[Test]
    public function it_strictly_isolates_stock_analytics_between_tenants()
    {
        // Tenant A: 10 units (5 available, 2 reserved, 3 sold)
        for ($i = 1; $i <= 10; $i++) {
            Unit::create([
                'tenant_id' => $this->tenantA->id,
                'property_id' => $this->propertyA->id,
                'reference' => "LOT-A-{$i}",
                'typology' => 'T3',
                'area' => 80.0,
                'price' => 50000000,
                'status' => $i <= 5 ? 'available' : ($i <= 7 ? 'reserved' : 'sold'),
            ]);
        }

        // Tenant B: 100 units
        for ($i = 1; $i <= 100; $i++) {
            Unit::create([
                'tenant_id' => $this->tenantB->id,
                'property_id' => $this->propertyB->id,
                'reference' => "LOT-B-{$i}",
                'typology' => 'T4',
                'area' => 120.0,
                'price' => 90000000,
                'status' => 'available',
            ]);
        }

        // Run as Tenant A
        $this->actingAs($this->userA);
        /** @var StockAnalyticsService $service */
        $service = app(StockAnalyticsService::class);
        $overviewA = $service->overview();

        $this->assertEquals(10, $overviewA->totalUnits, 'Tenant A must see exactly 10 units');
        $this->assertEquals(5, $overviewA->availableUnits);
        $this->assertEquals(2, $overviewA->reservedUnits);
        $this->assertEquals(3, $overviewA->soldUnits);
        $this->assertEquals(30.0, $overviewA->sellThroughRate); // 3 / 10 = 30%
        $this->assertEquals(20.0, $overviewA->reservationRate); // 2 / 10 = 20%
        $this->assertEquals(50.0, $overviewA->availabilityRate); // 5 / 10 = 50%
        $this->assertEquals(500000000.0, $overviewA->totalStockValue);
        $this->assertEquals(250000000.0, $overviewA->availableStockValue);

        // Run as Tenant B
        $this->actingAs($this->userB);
        $overviewB = $service->overview();

        $this->assertEquals(100, $overviewB->totalUnits, 'Tenant B must see exactly 100 units');
        $this->assertEquals(100, $overviewB->availableUnits);
        $this->assertEquals(0, $overviewB->reservedUnits);
        $this->assertEquals(0, $overviewB->soldUnits);
        $this->assertEquals(0.0, $overviewB->sellThroughRate);
    }

    #[Test]
    public function it_calculates_exact_status_breakdown_and_integrity()
    {
        $this->actingAs($this->userA);

        // 3 available, 2 reserved, 5 sold = 10 total
        for ($i = 1; $i <= 10; $i++) {
            Unit::create([
                'tenant_id' => $this->tenantA->id,
                'property_id' => $this->propertyA->id,
                'reference' => "LOT-ST-{$i}",
                'typology' => 'T3',
                'area' => 75.0,
                'price' => 40000000,
                'status' => $i <= 3 ? 'available' : ($i <= 5 ? 'reserved' : 'sold'),
            ]);
        }

        /** @var StockAnalyticsService $service */
        $service = app(StockAnalyticsService::class);
        $overview = $service->overview();

        $this->assertEquals(10, $overview->totalUnits);
        $this->assertEquals(3, $overview->availableUnits);
        $this->assertEquals(2, $overview->reservedUnits);
        $this->assertEquals(5, $overview->soldUnits);
        $this->assertEquals(50.0, $overview->sellThroughRate); // 5 / 10 = 50%
        $this->assertEquals(30.0, $overview->availabilityRate);
        $this->assertEquals(20.0, $overview->reservationRate);
    }

    #[Test]
    public function it_calculates_strictly_weighted_price_per_sqm()
    {
        $this->actingAs($this->userA);

        // Lot 1: 100M FCFA, 50 m² -> 2 000 000 FCFA/m²
        Unit::create([
            'tenant_id' => $this->tenantA->id,
            'property_id' => $this->propertyA->id,
            'reference' => 'LOT-W1',
            'typology' => 'T2',
            'area' => 50.0,
            'price' => 100000000,
            'status' => 'available',
        ]);

        // Lot 2: 50M FCFA, 100 m² -> 500 000 FCFA/m²
        Unit::create([
            'tenant_id' => $this->tenantA->id,
            'property_id' => $this->propertyA->id,
            'reference' => 'LOT-W2',
            'typology' => 'T4',
            'area' => 100.0,
            'price' => 50000000,
            'status' => 'available',
        ]);

        // Simple average would be: (2 000 000 + 500 000) / 2 = 1 250 000 FCFA/m²
        // Weighted average is: (100M + 50M) / (50 + 100) = 150M / 150 m² = 1 000 000 FCFA/m²!
        /** @var StockAnalyticsService $service */
        $service = app(StockAnalyticsService::class);
        $overview = $service->overview();

        $this->assertEquals(1000000.0, $overview->weightedPricePerSqm, 'Must strictly compute weighted price per sqm');
        $this->assertNotEquals(1250000.0, $overview->weightedPricePerSqm, 'Must NOT compute naive simple average');
    }

    #[Test]
    public function it_handles_zero_surface_without_division_by_zero()
    {
        $this->actingAs($this->userA);

        Unit::create([
            'tenant_id' => $this->tenantA->id,
            'property_id' => $this->propertyA->id,
            'reference' => 'LOT-ZERO',
            'typology' => 'Terrain',
            'area' => 0.0,
            'price' => 20000000,
            'status' => 'available',
        ]);

        /** @var StockAnalyticsService $service */
        $service = app(StockAnalyticsService::class);
        $overview = $service->overview();

        $this->assertEquals(0.0, $overview->weightedPricePerSqm);
        $this->assertEquals(0.0, $overview->averageSurface);
    }

    #[Test]
    public function it_filters_stock_by_property()
    {
        $this->actingAs($this->userA);

        $prop2 = Property::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Résidence Oasis',
            'city' => 'Dakar',
            'status' => 'available',
        ]);

        // 3 units in property A
        for ($i = 1; $i <= 3; $i++) {
            Unit::create([
                'tenant_id' => $this->tenantA->id,
                'property_id' => $this->propertyA->id,
                'reference' => "LOT-PA-{$i}",
                'area' => 60,
                'price' => 30000000,
                'status' => 'available',
            ]);
        }

        // 5 units in property 2
        for ($i = 1; $i <= 5; $i++) {
            Unit::create([
                'tenant_id' => $this->tenantA->id,
                'property_id' => $prop2->id,
                'reference' => "LOT-P2-{$i}",
                'area' => 90,
                'price' => 50000000,
                'status' => 'available',
            ]);
        }

        /** @var StockAnalyticsService $service */
        $service = app(StockAnalyticsService::class);

        $filterAll = new AnalyticsFilterData();
        $this->assertEquals(8, $service->overview($filterAll)->totalUnits);

        $filterProp2 = new AnalyticsFilterData(propertyId: $prop2->id);
        $this->assertEquals(5, $service->overview($filterProp2)->totalUnits);
    }

    #[Test]
    public function it_filters_stock_by_typology()
    {
        $this->actingAs($this->userA);

        Unit::create([
            'tenant_id' => $this->tenantA->id,
            'property_id' => $this->propertyA->id,
            'reference' => 'T2-1',
            'typology' => 'T2',
            'area' => 50,
            'price' => 35000000,
            'status' => 'available',
        ]);

        for ($i = 1; $i <= 4; $i++) {
            Unit::create([
                'tenant_id' => $this->tenantA->id,
                'property_id' => $this->propertyA->id,
                'reference' => "T3-{$i}",
                'typology' => 'T3',
                'area' => 85,
                'price' => 60000000,
                'status' => 'available',
            ]);
        }

        /** @var StockAnalyticsService $service */
        $service = app(StockAnalyticsService::class);

        $filterT3 = new AnalyticsFilterData(unitType: 'T3');
        $this->assertEquals(4, $service->overview($filterT3)->totalUnits);

        $byType = $service->byType();
        $this->assertCount(2, $byType); // T2 and T3
    }

    #[Test]
    public function it_correctly_classifies_stock_into_aging_buckets()
    {
        $this->actingAs($this->userA);

        // 5 units with distinct ages in days
        $ages = [15, 45, 120, 250, 400];
        foreach ($ages as $index => $age) {
            $unit = Unit::create([
                'tenant_id' => $this->tenantA->id,
                'property_id' => $this->propertyA->id,
                'reference' => "LOT-AGE-{$index}",
                'area' => 70,
                'price' => 45000000,
                'status' => 'available',
                'marketed_at' => Carbon::now()->subDays($age),
            ]);
        }

        /** @var StockAnalyticsService $service */
        $service = app(StockAnalyticsService::class);
        $aging = $service->aging();

        $this->assertEquals(1, $aging->bucket0to30, 'Bucket 0-30j must contain 15d unit');
        $this->assertEquals(1, $aging->bucket31to90, 'Bucket 31-90j must contain 45d unit');
        $this->assertEquals(1, $aging->bucket91to180, 'Bucket 91-180j must contain 120d unit');
        $this->assertEquals(1, $aging->bucket181to365, 'Bucket 181-365j must contain 250d unit');
        $this->assertEquals(1, $aging->bucketOver365, 'Bucket >365j must contain 400d unit');

        // Average: (15 + 45 + 120 + 250 + 400) / 5 = 830 / 5 = 166.0 days
        $this->assertEquals(166.0, $aging->averageDays);
    }

    #[Test]
    public function it_renders_all_stock_intelligence_widgets_without_errors()
    {
        $this->actingAs($this->userA);

        Unit::create([
            'tenant_id' => $this->tenantA->id,
            'property_id' => $this->propertyA->id,
            'reference' => 'LOT-WIDGET',
            'typology' => 'T3',
            'area' => 80,
            'price' => 50000000,
            'status' => 'available',
        ]);

        // 1. StockOverviewWidget
        $overviewWidget = new StockOverviewWidget();
        $stats = invade($overviewWidget)->getStats();
        $this->assertNotEmpty($stats);
        $this->assertCount(7, $stats);

        // 2. StockByProgramWidget
        $byProgramWidget = new StockByProgramWidget();
        $dataProg = invade($byProgramWidget)->getData();
        $this->assertArrayHasKey('datasets', $dataProg);
        $this->assertArrayHasKey('labels', $dataProg);

        // 3. StockByTypeWidget
        $byTypeWidget = new StockByTypeWidget();
        $dataType = invade($byTypeWidget)->getData();
        $this->assertArrayHasKey('datasets', $dataType);
        $this->assertArrayHasKey('labels', $dataType);

        // 4. StockAgingWidget
        $agingWidget = new StockAgingWidget();
        $dataAge = invade($agingWidget)->getData();
        $this->assertArrayHasKey('datasets', $dataAge);
        $this->assertArrayHasKey('labels', $dataAge);
        $this->assertCount(5, $dataAge['labels']);
    }
}
