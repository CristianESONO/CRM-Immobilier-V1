<?php

namespace Tests\Feature;

use App\Events\CommissionCalculated;
use App\Events\CommissionPaid;
use App\Models\BuyerPortalAccess;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Contact;
use App\Models\PartnerPortalAccess;
use App\Models\Property;
use App\Models\Referrer;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Partners\CommissionEngineService;
use App\Services\Partners\PartnerAttributionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PartnerManagementAndCommissionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Source $source;
    private Property $property;
    private Referrer $partner;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-20')->startOfDay());

        $this->tenant = Tenant::create(['name' => 'Promotion Partenaires', 'slug' => 'part-v7']);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'DA Partenariats',
            'email' => 'partenaires@v7.test',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->source = Source::create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'referrer',
            'label' => 'Apporteur Teranga',
        ]);
        $this->property = Property::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Résidence Palmier',
            'location' => 'Dakar',
        ]);

        $this->partner = Referrer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabinet Immobilier Teranga',
            'type' => 'agency',
            'organisation' => 'Teranga SARL',
            'phone' => '+221338000000',
            'email' => 'contact@teranga.sn',
            'status' => 'active',
            'agreement_number' => 'CONV-2026-001',
            'agreement_signed_at' => Carbon::parse('2026-01-15'),
            'bank_details' => ['iban' => 'SN0800100200300'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function it_calculates_commission_automatically_and_dispatches_event(): void
    {
        Event::fake([CommissionCalculated::class]);

        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'referrer_id' => $this->partner->id,
            'first_name' => 'Mamadou',
            'last_name' => 'Ba',
        ]);

        $unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->property->id,
            'reference' => 'LOT-V7-01',
            'area' => 110,
            'price' => 60_000_000,
            'status' => 'reserved',
        ]);

        $reservation = Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reference' => 'RES-V7-01',
            'contact_id' => $contact->id,
            'property_id' => $this->property->id,
            'unit_id' => $unit->id,
            'assigned_to' => $this->user->id,
            'status' => 'confirmed',
            'total_amount' => 60_000_000,
            'deposit_amount' => 0,
            'reserved_at' => Carbon::now(),
        ]);

        // Règle de commissionnement à 3%
        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Taux Agences 3%',
            'rule_type' => 'percentage',
            'rate' => 3.00,
            'is_active' => true,
        ]);

        $service = app(CommissionEngineService::class);
        $commission = $service->calculateCommission($reservation, $this->user);

        $this->assertNotNull($commission);
        $this->assertEquals(60_000_000.0, $commission->sales_amount);
        $this->assertEquals(1_800_000.0, $commission->commission_amount); // 3% de 60M
        $this->assertEquals('calculated', $commission->status);

        Event::assertDispatched(CommissionCalculated::class);
    }

    #[Test]
    public function it_executes_commission_lifecycle_transitions_strictly(): void
    {
        Event::fake([CommissionPaid::class]);

        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'referrer_id' => $this->partner->id,
            'first_name' => 'Samba',
            'last_name' => 'Diallo',
        ]);

        $unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->property->id,
            'reference' => 'LOT-V7-02',
            'area' => 80,
            'price' => 40_000_000,
            'status' => 'reserved',
        ]);

        $reservation = Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reference' => 'RES-V7-02',
            'contact_id' => $contact->id,
            'property_id' => $this->property->id,
            'unit_id' => $unit->id,
            'assigned_to' => $this->user->id,
            'status' => 'confirmed',
            'total_amount' => 40_000_000,
            'deposit_amount' => 0,
            'reserved_at' => Carbon::now(),
        ]);

        $service = app(CommissionEngineService::class);
        $commission = $service->calculateCommission($reservation, $this->user);

        // 1. Validated
        $service->validateCommission($commission, $this->user);
        $this->assertEquals('validated', $commission->fresh()->status);
        $this->assertNotNull($commission->fresh()->validated_at);

        // 2. Payable
        $service->markPayable($commission, $this->user);
        $this->assertEquals('payable', $commission->fresh()->status);
        $this->assertNotNull($commission->fresh()->payable_at);

        // 3. Paid
        $service->markPaid($commission, 'VIR-2026-999', $this->user);
        $this->assertEquals('paid', $commission->fresh()->status);
        $this->assertEquals('VIR-2026-999', $commission->fresh()->payment_reference);
        $this->assertNotNull($commission->fresh()->paid_at);

        // Vérification de l'historique d'audit
        $this->assertGreaterThanOrEqual(4, $commission->histories()->count());
        Event::assertDispatched(CommissionPaid::class);
    }

    #[Test]
    public function partner_attribution_validates_90_day_window(): void
    {
        $attributionService = app(PartnerAttributionService::class);

        // Lead récent (20 jours) -> Valide
        $recentContact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'referrer_id' => $this->partner->id,
            'first_name' => 'Récent',
            'last_name' => 'Test',
        ]);
        $recentContact->created_at = Carbon::now()->subDays(20);
        $recentContact->save();

        $this->assertTrue($attributionService->isAttributionValid($recentContact, 90));

        // Lead ancien (120 jours) -> Expiré
        $oldContact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'referrer_id' => $this->partner->id,
            'first_name' => 'Ancien',
            'last_name' => 'Test',
        ]);
        $oldContact->created_at = Carbon::now()->subDays(120);
        $oldContact->save();

        $this->assertFalse($attributionService->isAttributionValid($oldContact, 90));
    }

    #[Test]
    public function portal_token_hashing_secures_portal_access(): void
    {
        $rawToken = 'token_secret_12345';
        $hashedToken = PartnerPortalAccess::hashToken($rawToken);

        $this->assertEquals(hash('sha256', 'token_secret_12345'), $hashedToken);
        $this->assertEquals(64, strlen($hashedToken));

        $buyerHashed = BuyerPortalAccess::hashToken($rawToken);
        $this->assertEquals($hashedToken, $buyerHashed);
    }
}
