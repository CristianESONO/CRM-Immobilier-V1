<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Property;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\PropertyMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityAndMatchingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function opportunity_belongs_to_tenant_and_is_scoped()
    {
        $tenantA = Tenant::create(['slug' => 'tenant-a', 'name' => 'Promoteur A']);
        $tenantB = Tenant::create(['slug' => 'tenant-b', 'name' => 'Promoteur B']);

        // Set session for Tenant A
        session(['tenant_id' => $tenantA->id]);

        $sourceA = Source::create(['tenant_id' => $tenantA->id, 'channel' => 'web', 'label' => 'Web A']);
        $contactA = Contact::create([
            'tenant_id' => $tenantA->id,
            'source_id' => $sourceA->id,
            'first_name' => 'Moussa',
            'last_name' => 'Diop',
        ]);

        $opportunityA = Opportunity::create([
            'tenant_id' => $tenantA->id,
            'contact_id' => $contactA->id,
            'title' => 'Achat Villa Almadies',
            'stage' => 'decouverte',
            'amount' => 85000000,
        ]);

        $this->assertEquals($tenantA->id, $opportunityA->tenant_id);
        $this->assertCount(1, Opportunity::all());

        // Switch to Tenant B
        session(['tenant_id' => $tenantB->id]);

        // Tenant B must see 0 opportunities
        $this->assertCount(0, Opportunity::all());
    }

    /** @test */
    public function opportunity_can_be_associated_with_contact_property_and_unit()
    {
        $tenant = Tenant::create(['slug' => 'tenant-prop', 'name' => 'Promoteur Test']);
        session(['tenant_id' => $tenant->id]);

        $source = Source::create(['tenant_id' => $tenant->id, 'channel' => 'web', 'label' => 'Web Lead']);
        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Fatou',
            'last_name' => 'Sow',
        ]);

        $property = Property::create([
            'tenant_id' => $tenant->id,
            'name' => 'Résidence La Corniche',
            'location' => 'Dakar Almadies',
            'property_type' => 'apartment',
        ]);

        $unit = Unit::create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
            'reference' => 'LOT-B204',
            'area' => 110.5,
            'price' => 75000000,
            'status' => 'available',
        ]);

        $opportunity = Opportunity::create([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'title' => 'Acquisition F4 La Corniche',
            'stage' => 'visite_planifiee',
            'amount' => $unit->price,
        ]);

        $this->assertEquals('LOT-B204', $opportunity->unit->reference);
        $this->assertEquals('Résidence La Corniche', $opportunity->property->name);
        $this->assertEquals('Fatou', $opportunity->contact->first_name);
        $this->assertCount(1, $contact->fresh()->opportunities);
        $this->assertCount(1, $unit->fresh()->opportunities);
    }

    /** @test */
    public function matching_service_finds_compatible_lots_and_excludes_unavailable()
    {
        $tenant = Tenant::create(['slug' => 'tenant-match', 'name' => 'Promoteur Matching']);
        session(['tenant_id' => $tenant->id]);

        $source = Source::create(['tenant_id' => $tenant->id, 'channel' => 'web', 'label' => 'Web']);

        // Contact looking for villa in Almadies with max budget 100M
        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Ibrahima',
            'last_name' => 'Ba',
            'property_type' => 'villa',
            'district' => 'Almadies',
            'budget_max' => 100000000,
        ]);

        // Program 1: Villas in Almadies
        $propertyAlmadies = Property::create([
            'tenant_id' => $tenant->id,
            'name' => 'Les Villas du Phare',
            'location' => 'Dakar, Almadies',
            'property_type' => 'villa',
        ]);

        // Unit 1: Available, 90M (perfect match)
        $unitPerfect = Unit::create([
            'tenant_id' => $tenant->id,
            'property_id' => $propertyAlmadies->id,
            'reference' => 'VILLA-01',
            'area' => 250,
            'price' => 90000000,
            'status' => 'available',
        ]);

        // Unit 2: Sold, 85M (must be excluded because status != available)
        $unitSold = Unit::create([
            'tenant_id' => $tenant->id,
            'property_id' => $propertyAlmadies->id,
            'reference' => 'VILLA-02-SOLD',
            'area' => 250,
            'price' => 85000000,
            'status' => 'sold',
        ]);

        // Program 2: Apartments in Plateau (different type & district)
        $propertyPlateau = Property::create([
            'tenant_id' => $tenant->id,
            'name' => 'Tour Plateau',
            'location' => 'Dakar, Plateau',
            'property_type' => 'apartment',
        ]);

        $unitPlateau = Unit::create([
            'tenant_id' => $tenant->id,
            'property_id' => $propertyPlateau->id,
            'reference' => 'APPART-101',
            'area' => 80,
            'price' => 150000000, // over budget
            'status' => 'available',
        ]);

        $service = new PropertyMatchingService();
        $matches = $service->findMatchingUnitsForContact($contact);

        // Should return unitPerfect as top match
        $this->assertNotEmpty($matches);
        $topMatch = $matches->first();
        $this->assertEquals($unitPerfect->id, $topMatch['unit']->id);
        $this->assertEquals(100, $topMatch['score']);
        $this->assertTrue($topMatch['criteria']['budget']);
        $this->assertTrue($topMatch['criteria']['type']);
        $this->assertTrue($topMatch['criteria']['district']);

        // Must not contain sold unit
        $this->assertFalse($matches->pluck('unit.id')->contains($unitSold->id));
    }
}
