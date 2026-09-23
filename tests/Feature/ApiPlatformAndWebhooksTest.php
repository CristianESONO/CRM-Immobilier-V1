<?php

namespace Tests\Feature;

use App\Events\CommissionPaid;
use App\Events\ContractSigned;
use App\Events\KycDocumentVerified;
use App\Events\PaymentRecorded;
use App\Events\ReservationCreated;
use App\Http\Middleware\ApiScopeMiddleware;
use App\Jobs\DispatchOutboundWebhookJob;
use App\Models\ApiKey;
use App\Models\BuyerDocument;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractVersion;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Referrer;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Services\Observability\AppObservabilityService;
use App\Services\Partners\CommissionEngineService;
use App\Services\Webhooks\OutboundWebhookDispatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiPlatformAndWebhooksTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Source $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Promoteur V8', 'slug' => 'promoteur-v8']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->source = Source::create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'referrer',
            'label' => 'Apporteur Teranga',
        ]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function v7_hardening_commission_snapshots_and_rule_changes_isolation()
    {
        $referrer = Referrer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabinet Conseil',
            'type' => 'broker',
        ]);

        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'referrer_id' => $referrer->id,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Règle Standard 5%',
            'rate' => 5.00,
            'is_active' => true,
        ]);

        $property = Property::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Résidence Horizon',
            'city' => 'Dakar',
        ]);

        $unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'reference' => 'A101',
            'typology' => 'T3',
            'status' => 'available',
            'price' => 100000.00,
            'surface' => 80.00,
        ]);

        $reservation = Reservation::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'reference' => 'RES-V7-001',
            'unit_id' => $unit->id,
            'contact_id' => $contact->id,
            'user_id' => $this->user->id,
            'total_amount' => 100000.00,
            'status' => 'option',
        ]);

        $service = new CommissionEngineService();
        $commission = $service->calculateCommission($reservation, $this->user);

        $this->assertNotNull($commission);
        $this->assertEquals(5.00, (float) $commission->rate_snapshot);
        $this->assertEquals('Règle Standard 5%', $commission->rule_name_snapshot);
        $this->assertEquals(5000.00, (float) $commission->commission_amount);

        // Modify CommissionRule afterwards
        $rule->update(['rate' => 10.00, 'name' => 'Nouvelle Règle 10%']);

        // Historical commission rate and amount must remain untouched
        $commission->refresh();
        $this->assertEquals(5.00, (float) $commission->rate_snapshot);
        $this->assertEquals('Règle Standard 5%', $commission->rule_name_snapshot);
        $this->assertEquals(5000.00, (float) $commission->commission_amount);
    }

    /** @test */
    public function v7_hardening_paid_commission_is_terminal_and_immutable()
    {
        $referrer = Referrer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Apporteur Hardened',
            'type' => 'agency',
        ]);

        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'referrer_id' => $referrer->id,
        ]);

        $property = Property::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Villa Palm',
            'city' => 'Abidjan',
        ]);

        $unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'reference' => 'V01',
            'typology' => 'Villa',
            'status' => 'available',
            'price' => 200000.00,
            'surface' => 120.00,
        ]);

        $reservation = Reservation::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'reference' => 'RES-V7-002',
            'unit_id' => $unit->id,
            'contact_id' => $contact->id,
            'user_id' => $this->user->id,
            'total_amount' => 200000.00,
            'status' => 'option',
        ]);

        $service = new CommissionEngineService();
        $commission = $service->calculateCommission($reservation, $this->user);
        $service->validateCommission($commission, $this->user);
        $service->markPayable($commission, $this->user);

        // Mark as paid
        $service->markPaid($commission, 'VIR-2026-99', $this->user, 'Règlement final', 'bank_transfer');

        $commission->refresh();
        $this->assertEquals('paid', $commission->status);
        $this->assertEquals('VIR-2026-99', $commission->payment_reference);
        $this->assertEquals($this->user->id, $commission->paid_by_user_id);
        $this->assertEquals('bank_transfer', $commission->payment_method);

        // Expect Exception if attempting to mark paid again or validate
        $this->expectException(\LogicException::class);
        $service->markPaid($commission, 'VIR-RETRY', $this->user);
    }

    /** @test */
    public function v8_1_api_platform_scope_middleware_authorizes_and_rejects()
    {
        $rawToken = 'test-secret-api-token-12345';
        $keyHash = hash('sha256', $rawToken);

        ApiKey::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Clé Intégration ERP',
            'key_hash' => $keyHash,
            'scopes' => ['leads:read', 'leads:write'],
        ]);

        // Request without API Key -> 401
        $response = $this->getJson('/api/v1/scoped/properties');
        $response->assertStatus(401);

        // Request with API Key lacking required scope 'properties:read' -> 403
        $responseWithKey = $this->withHeaders([
            'X-API-Key' => $rawToken,
            'X-Tenant-ID' => $this->tenant->id,
        ])->getJson('/api/v1/scoped/properties');

        $responseWithKey->assertStatus(403)
            ->assertJsonPath('status', 'error');

        // Update key to include wildcard '*' scope
        $apiKey = ApiKey::where('key_hash', $keyHash)->first();
        $apiKey->update(['scopes' => ['*']]);

        // Request with wildcard scope -> 200
        $responseAuthorized = $this->withHeaders([
            'X-API-Key' => $rawToken,
            'X-Tenant-ID' => $this->tenant->id,
        ])->getJson('/api/v1/scoped/properties');

        $responseAuthorized->assertStatus(200);
    }

    /** @test */
    public function v8_2_outbound_webhook_engine_dispatches_events_with_hmac_signature()
    {
        Http::fake([
            'https://external-erp.com/webhooks' => Http::response(['received' => true], 200),
        ]);

        $subscription = WebhookSubscription::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Système ERP Externe',
            'url' => 'https://external-erp.com/webhooks',
            'secret' => 'super-secret-hmac-key',
            'events' => ['ReservationCreated', 'PaymentRecorded'],
            'is_active' => true,
        ]);

        $property = Property::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Résidence Atlantique',
            'city' => 'Casablanca',
        ]);

        $unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'reference' => 'B202',
            'typology' => 'T3',
            'status' => 'available',
            'price' => 150000.00,
            'surface' => 90.00,
        ]);

        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'first_name' => 'Marc',
            'last_name' => 'Moreau',
        ]);

        $reservation = Reservation::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'reference' => 'RES-WH-100',
            'unit_id' => $unit->id,
            'contact_id' => $contact->id,
            'user_id' => $this->user->id,
            'total_amount' => 150000.00,
            'status' => 'option',
        ]);

        // Trigger domain event ReservationCreated
        event(new ReservationCreated($reservation, $this->user));

        $delivery = WebhookDelivery::where('tenant_id', $this->tenant->id)
            ->where('event_type', 'ReservationCreated')
            ->first();

        $this->assertNotNull($delivery);
        $this->assertEquals('delivered', $delivery->status);
        $this->assertEquals(200, $delivery->status_code);

        // Assert HTTP payload signature
        Http::assertSent(function ($request) use ($subscription) {
            $expectedSignature = hash_hmac('sha256', $request->body(), $subscription->secret);
            return $request->url() === 'https://external-erp.com/webhooks' &&
                   $request->header('X-CRM-Signature')[0] === $expectedSignature &&
                   $request->header('X-CRM-Event')[0] === 'ReservationCreated';
        });
    }

    /** @test */
    public function v8_4_app_observability_metrics_endpoint_returns_health_metrics()
    {
        $property = Property::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tour Lumina',
            'city' => 'Abidjan',
        ]);

        $unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'reference' => 'TL-10',
            'typology' => 'T4',
            'status' => 'available',
            'price' => 300000.00,
            'surface' => 150.00,
        ]);

        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'first_name' => 'Awa',
            'last_name' => 'Diallo',
        ]);

        Reservation::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'reference' => 'RES-OBS-1',
            'unit_id' => $unit->id,
            'contact_id' => $contact->id,
            'user_id' => $this->user->id,
            'total_amount' => 300000.00,
            'status' => 'option',
        ]);

        $response = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->id,
        ])->getJson('/api/v1/metrics');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.business_metrics.total_reservations', 1);
    }
}
