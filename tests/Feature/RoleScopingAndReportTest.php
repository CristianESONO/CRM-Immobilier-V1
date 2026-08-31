<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleScopingAndReportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function commercial_user_only_sees_contacts_assigned_to_them()
    {
        $tenant = Tenant::create(['slug' => 'tenant-role', 'name' => 'Tenant Role']);
        session(['tenant_id' => $tenant->id]);

        $commA = User::create([
            'name' => 'Commercial A',
            'email' => 'comma@gret.sn',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'commercial',
        ]);

        $commB = User::create([
            'name' => 'Commercial B',
            'email' => 'commb@gret.sn',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'commercial',
        ]);

        $source = Source::create(['tenant_id' => $tenant->id, 'channel' => 'web', 'label' => 'Web LP']);

        $contactA = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Prospect A',
            'assigned_to' => $commA->id,
        ]);

        $contactB = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Prospect B',
            'assigned_to' => $commB->id,
        ]);

        // Authenticate as Commercial A
        $this->actingAs($commA);

        $queryResult = \App\Filament\Resources\ContactResource::getEloquentQuery()->get();

        $this->assertCount(1, $queryResult);
        $this->assertEquals($contactA->id, $queryResult->first()->id);
        $this->assertFalse($queryResult->contains('id', $contactB->id));
    }

    /** @test */
    public function committee_pdf_report_route_renders_successfully()
    {
        $tenant = Tenant::create(['slug' => 'tenant-report', 'name' => 'Tenant Report']);
        session(['tenant_id' => $tenant->id]);

        $response = $this->get('/reports/committee-pdf');
        $response->assertStatus(200);
        $response->assertSee('RAPPORT DE COMITÉ COMMERCIAL');
    }
}
