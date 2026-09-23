<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Infrastructure\BackupService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductionCertificationTest extends TestCase
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

        $this->tenant = Tenant::create(['name' => 'Promotion Certification', 'slug' => 'cert-prod']);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Certif',
            'email' => 'admin@certif.test',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->source = Source::create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'web',
            'label' => 'Web Certif',
        ]);
        $this->property = Property::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Résidence Certif',
            'location' => 'Dakar',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function it_prevents_double_booking_under_concurrent_transactions(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'first_name' => 'Acheteur',
            'last_name' => 'Concurrent',
        ]);

        $unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->property->id,
            'reference' => 'LOT-LOCK-001',
            'area' => 90,
            'price' => 25_000_000,
            'status' => 'available',
        ]);

        $service = app(ReservationService::class);

        // Transaction 1 réussie
        $resa1 = $service->createReservation([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'property_id' => $this->property->id,
            'unit_id' => $unit->id,
            'assigned_to' => $this->user->id,
            'status' => 'confirmed',
            'total_amount' => 25_000_000,
        ], []);

        $this->assertEquals('reserved', $unit->fresh()->status);

        // Transaction 2 simultanée doit lever une exception
        $this->expectException(\Exception::class);
        $service->createReservation([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'property_id' => $this->property->id,
            'unit_id' => $unit->id,
            'assigned_to' => $this->user->id,
            'status' => 'confirmed',
            'total_amount' => 25_000_000,
        ], []);
    }

    #[Test]
    public function it_creates_and_restores_tenant_backup_snapshot(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'first_name' => 'Backup',
            'last_name' => 'User',
        ]);

        $unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->property->id,
            'reference' => 'LOT-BCK-001',
            'area' => 80,
            'price' => 20_000_000,
            'status' => 'available',
        ]);

        $backupService = app(BackupService::class);
        $res = $backupService->createTenantBackup($this->tenant);

        $this->assertTrue($res['success']);
        $this->assertNotEmpty($res['backup_key']);
        $this->assertNotEmpty($res['checksum']);

        // Simulation de suppression
        Contact::where('tenant_id', $this->tenant->id)->delete();
        $this->assertEquals(0, Contact::where('tenant_id', $this->tenant->id)->count());

        // Restauration
        $restored = $backupService->restoreTenantBackup($res['backup_key']);
        $this->assertTrue($restored);
        $this->assertEquals(1, Contact::where('tenant_id', $this->tenant->id)->count());
    }
}
