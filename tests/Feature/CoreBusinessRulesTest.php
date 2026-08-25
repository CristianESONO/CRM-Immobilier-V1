<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BusinessHours;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function contact_requires_mandatory_source()
    {
        $tenant = Tenant::create(['slug' => 'test-tenant', 'name' => 'Test Tenant']);
        session(['tenant_id' => $tenant->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Intentionally missing source_id
        Contact::create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'phone_e164' => '+221771234567',
        ]);
    }

    /** @test */
    public function business_hours_counter_handles_weekend_overlap_correctly()
    {
        // Friday 18:00
        $fridayEvening = Carbon::create(2026, 8, 21, 18, 0, 0, 'Africa/Dakar');
        // Monday 09:00
        $mondayMorning = Carbon::create(2026, 8, 24, 9, 0, 0, 'Africa/Dakar');

        $minutes = BusinessHours::diffInMinutes($fridayEvening, $mondayMorning);

        // Expected: 60 minutes (1 hour from Monday 8h to 9h), NOT 63 hours!
        $this->assertEquals(60, $minutes);
    }

    /** @test */
    public function qualification_is_strictly_calculated_from_four_conditions()
    {
        $tenant = Tenant::create(['slug' => 'tenant-qualif', 'name' => 'Tenant Qualif']);
        session(['tenant_id' => $tenant->id]);

        $source = Source::create(['tenant_id' => $tenant->id, 'channel' => 'web', 'label' => 'Web LP']);

        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Awa',
            'last_name' => 'Ndiaye',
        ]);

        $this->assertNull($contact->qualified_at);

        // Fill 3 conditions only
        $contact->update([
            'q_replied_at' => now(),
            'q_project_at' => now(),
            'q_budget_at' => now(),
        ]);
        $this->assertNull($contact->fresh()->qualified_at);

        // Fill 4th condition
        $contact->update([
            'q_source_at' => now(),
        ]);
        $this->assertNotNull($contact->fresh()->qualified_at);

        // Uncheck one condition -> qualified_at must revert to null
        $contact->update([
            'q_budget_at' => null,
        ]);
        $this->assertNull($contact->fresh()->qualified_at);
    }
}
