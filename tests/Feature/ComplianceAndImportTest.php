<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Source;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceAndImportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function contact_can_be_deleted_for_senegal_2008_12_compliance()
    {
        $tenant = Tenant::create(['slug' => 'tenant-compliance', 'name' => 'Tenant Compliance']);
        session(['tenant_id' => $tenant->id]);

        $source = Source::create(['tenant_id' => $tenant->id, 'channel' => 'web', 'label' => 'Web LP']);

        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Samba',
            'last_name' => 'Gaye',
            'phone_e164' => '+221774445566',
            'email' => 'samba.gaye@example.sn',
        ]);

        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);

        // Right to be forgotten (Loi 2008-12)
        $contact->delete();

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }
}
