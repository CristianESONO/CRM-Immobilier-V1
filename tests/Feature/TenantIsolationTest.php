<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Property;
use App\Models\Source;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function tenant_a_cannot_view_or_access_tenant_b_contacts()
    {
        $tenantA = Tenant::create(['slug' => 'tenant-a', 'name' => 'Tenant A']);
        $tenantB = Tenant::create(['slug' => 'tenant-b', 'name' => 'Tenant B']);

        session(['tenant_id' => $tenantA->id]);
        $sourceA = Source::create(['tenant_id' => $tenantA->id, 'channel' => 'web', 'label' => 'Source A']);
        $contactA = Contact::create([
            'tenant_id' => $tenantA->id,
            'source_id' => $sourceA->id,
            'first_name' => 'Moussa',
            'last_name' => 'Diop',
            'phone_e164' => '+221770000001',
        ]);

        session(['tenant_id' => $tenantB->id]);
        $sourceB = Source::create(['tenant_id' => $tenantB->id, 'channel' => 'web', 'label' => 'Source B']);
        $contactB = Contact::create([
            'tenant_id' => $tenantB->id,
            'source_id' => $sourceB->id,
            'first_name' => 'Fatou',
            'last_name' => 'Sow',
            'phone_e164' => '+221770000002',
        ]);

        // When logged in as Tenant B, querying contacts should ONLY return Tenant B contacts
        $contactsForTenantB = Contact::all();
        $this->assertCount(1, $contactsForTenantB);
        $this->assertEquals($contactB->id, $contactsForTenantB->first()->id);
        $this->assertFalse($contactsForTenantB->contains('id', $contactA->id));

        // Attempting to find Tenant A contact directly while on Tenant B scope must fail
        $this->expectException(ModelNotFoundException::class);
        Contact::findOrFail($contactA->id);
    }

    /** @test */
    public function tenant_a_cannot_view_or_access_tenant_b_properties()
    {
        $tenantA = Tenant::create(['slug' => 'tenant-prop-a', 'name' => 'Tenant Prop A']);
        $tenantB = Tenant::create(['slug' => 'tenant-prop-b', 'name' => 'Tenant Prop B']);

        session(['tenant_id' => $tenantA->id]);
        $propA = Property::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Residence Teranga A',
            'location' => 'Almadies',
        ]);

        session(['tenant_id' => $tenantB->id]);
        $propB = Property::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Villa Baobab B',
            'location' => 'Saly',
        ]);

        $propertiesForTenantB = Property::all();
        $this->assertCount(1, $propertiesForTenantB);
        $this->assertEquals($propB->id, $propertiesForTenantB->first()->id);
        $this->assertFalse($propertiesForTenantB->contains('id', $propA->id));
    }
}
