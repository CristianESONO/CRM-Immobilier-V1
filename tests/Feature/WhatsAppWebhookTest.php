<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\Source;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function verify_endpoint_responds_with_hub_challenge_on_valid_token()
    {
        $response = $this->getJson('/api/v1/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=crm_whatsapp_verify_token_2026&hub_challenge=CHALLENGE_CODE_123');

        $response->assertStatus(200);
        $this->assertEquals('CHALLENGE_CODE_123', $response->getContent());
    }

    /** @test */
    public function incoming_whatsapp_message_marks_replied_and_stops_active_sequence()
    {
        $tenant = Tenant::create(['slug' => 'tenant-wa', 'name' => 'Tenant WA']);
        session(['tenant_id' => $tenant->id]);

        $source = Source::create(['tenant_id' => $tenant->id, 'channel' => 'whatsapp', 'label' => 'WhatsApp LP']);
        
        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Oumar',
            'last_name' => 'Kane',
            'phone_e164' => '+221778889900',
            'created_at' => now()->subMinutes(30),
        ]);

        $sequence = Sequence::create([
            'tenant_id' => $tenant->id,
            'key' => 'nouveau_contact',
            'name' => 'Séquence Nouveau Contact',
            'trigger' => 'new_contact',
            'steps' => [
                ['delay_hours' => 48, 'channel' => 'whatsapp', 'template' => 'relance_1'],
            ],
        ]);

        $enrollment = SequenceEnrollment::create([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'sequence_id' => $sequence->id,
            'status' => 'active',
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => '221778889900',
                                        'text' => [
                                            'body' => 'Bonjour, je suis intéressé par les appartements aux Almadies.',
                                        ],
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $contact->refresh();
        $enrollment->refresh();

        // 1. Condition q_replied_at must be updated
        $this->assertNotNull($contact->q_replied_at);

        // 2. SLA First response minutes must be computed
        $this->assertNotNull($contact->first_response_at);
        $this->assertGreaterThanOrEqual(0, $contact->first_response_minutes);

        // 3. Absolute Rule: Sequence enrollment MUST be stopped immediately
        $this->assertEquals('stopped', $enrollment->status);
        $this->assertEquals('prospect_replied', $enrollment->stop_reason);
    }
}
