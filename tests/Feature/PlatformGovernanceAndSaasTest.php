<?php

namespace Tests\Feature;

use App\Events\ReservationCreated;
use App\Jobs\DispatchOutboundWebhookJob;
use App\Models\ApiKey;
use App\Models\Contact;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Services\Governance\DataGovernanceService;
use App\Services\SaaS\TenantProvisioningService;
use App\Services\Webhooks\OutboundWebhookDispatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlatformGovernanceAndSaasTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Source $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Promoteur Governance V9',
            'slug' => 'promoteur-v9',
            'plan' => 'pro',
            'max_users' => 10,
            'max_properties' => 10,
        ]);

        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->source = Source::create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'direct',
            'label' => 'Site Web',
        ]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function v9_1_event_governance_attaches_event_id_and_correlation_id()
    {
        $property = Property::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Résidence Baobab',
            'city' => 'Dakar',
        ]);

        $unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'reference' => 'B101',
            'typology' => 'T2',
            'status' => 'available',
            'price' => 80000.00,
            'surface' => 60.00,
        ]);

        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'first_name' => 'Moussa',
            'last_name' => 'Sow',
        ]);

        $reservation = Reservation::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'reference' => 'RES-GOV-1',
            'unit_id' => $unit->id,
            'contact_id' => $contact->id,
            'user_id' => $this->user->id,
            'total_amount' => 80000.00,
            'status' => 'option',
        ]);

        $event = new ReservationCreated($reservation, $this->user);

        $this->assertStringStartsWith('evt_', $event->getEventId());
        $this->assertStringStartsWith('corr_', $event->getCorrelationId());
        $this->assertEquals('1.0', $event->getSchemaVersion());
    }

    /** @test */
    public function v9_2_webhook_management_supports_delivery_replay()
    {
        Http::fake([
            'https://erp-partner.com/webhook' => Http::response(['ok' => true], 200),
        ]);

        $subscription = WebhookSubscription::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ERP Replay Partner',
            'url' => 'https://erp-partner.com/webhook',
            'secret' => 'replay-secret-key',
            'events' => ['*'],
            'is_active' => true,
        ]);

        $delivery = WebhookDelivery::create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $subscription->id,
            'event_type' => 'ReservationCreated',
            'payload' => ['event' => 'ReservationCreated', 'id' => 123],
            'status' => 'failed',
            'attempts' => 3,
        ]);

        $service = new OutboundWebhookDispatcherService();
        $success = $service->replayDelivery($delivery->id);

        $this->assertTrue($success);
        $delivery->refresh();
        $this->assertEquals('delivered', $delivery->status);
        $this->assertEquals(200, $delivery->status_code);
    }

    /** @test */
    public function v9_3_api_governance_rejects_revoked_api_keys()
    {
        $rawToken = 'revoked-key-token-999';
        $keyHash = hash('sha256', $rawToken);

        ApiKey::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Clé Obsolète',
            'key_hash' => $keyHash,
            'scopes' => ['*'],
            'revoked_at' => now()->subDay(),
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $rawToken,
            'X-Tenant-ID' => $this->tenant->id,
        ])->getJson('/api/v1/scoped/properties');

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Clé d\'API révoquée.');
    }

    /** @test */
    public function v9_5_data_governance_anonymizes_contact_pii_and_exports_tenant_data()
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'first_name' => 'Fatou',
            'last_name' => 'Ndiaye',
            'email' => 'fatou@example.com',
            'phone' => '+221770000000',
        ]);

        $governanceService = new DataGovernanceService();
        $anonymized = $governanceService->anonymizeContact($contact);

        $this->assertEquals('Anonyme', $anonymized->first_name);
        $this->assertNull($anonymized->phone);
        $this->assertStringContainsString('@gdpr-delete.invalid', $anonymized->email);

        $exportData = $governanceService->exportTenantData($this->tenant);
        $this->assertEquals($this->tenant->id, $exportData['tenant']['id']);
        $this->assertArrayHasKey('summary', $exportData);
    }

    /** @test */
    public function v10_saas_tenant_provisioning_and_quota_limits_enforcement()
    {
        $provisioningService = new TenantProvisioningService();
        $newTenant = $provisioningService->provisionTenant('Promoteur Starter', 'promoteur-starter', 'starter');

        $this->assertEquals('starter', $newTenant->plan);
        $this->assertEquals(2, $newTenant->max_properties);
        $this->assertEquals(3, $newTenant->max_users);

        // Under limit check -> true
        $this->assertTrue($provisioningService->checkLimit($newTenant, 'properties'));

        // Fill properties up to limit (2)
        Property::create(['tenant_id' => $newTenant->id, 'name' => 'Prop 1', 'location' => 'Dakar']);
        Property::create(['tenant_id' => $newTenant->id, 'name' => 'Prop 2', 'location' => 'Dakar']);

        // Check limit again -> false
        $this->assertFalse($provisioningService->checkLimit($newTenant, 'properties'));
    }
}
