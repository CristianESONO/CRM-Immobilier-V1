<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Source;
use App\Models\Tenant;
use App\Services\OpenWaService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenWaServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function openwa_service_sends_text_message_using_yokalma_api()
    {
        Http::fake([
            'https://mywa.tickets-place.net/api/sessions/*/messages/send-text' => Http::response([
                'messageId' => 'true_221785962662@c.us_3EB0123',
                'timestamp' => 1783270317,
            ], 201),
        ]);

        $openWaService = new OpenWaService();
        $success = $openWaService->sendTextMessage('+221785962662', 'Test notification Yokalma');

        $this->assertTrue($success);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://mywa.tickets-place.net/api/sessions/1b9201d2-932d-4cae-8b5f-c58c1d9780a1/messages/send-text' &&
                   $request->hasHeader('Authorization', 'Bearer owa_k1_e05c74e13e2a679eae14d957458d798979f5a780c3fe36e76284969fd8c3c4b0') &&
                   $request['chatId'] === '221785962662' &&
                   $request['text'] === 'Test notification Yokalma';
        });
    }

    /** @test */
    public function openwa_service_fetches_session_status()
    {
        Http::fake([
            'https://mywa.tickets-place.net/api/sessions/*' => Http::response([
                'id' => '1b9201d2-932d-4cae-8b5f-c58c1d9780a1',
                'name' => 'yokalma',
                'status' => 'ready',
                'phone' => '221785962662',
            ], 200),
        ]);

        $openWaService = new OpenWaService();
        $statusInfo = $openWaService->getSessionStatus();

        $this->assertEquals('ready', $statusInfo['status']);
        $this->assertEquals('yokalma', $statusInfo['name']);
    }

    /** @test */
    public function whatsapp_service_dispatches_via_openwa_driver()
    {
        Http::fake([
            'https://mywa.tickets-place.net/api/sessions/*/messages/send-text' => Http::response(['messageId' => 'msg_123'], 200),
        ]);

        $tenant = Tenant::create(['slug' => 'tenant-wa-openwa', 'name' => 'Tenant OpenWA']);
        session(['tenant_id' => $tenant->id]);

        $source = Source::create(['tenant_id' => $tenant->id, 'channel' => 'whatsapp', 'label' => 'WhatsApp YokAlma']);
        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Moussa',
            'phone_e164' => '+221775556677',
        ]);

        $whatsAppService = app(WhatsAppService::class);
        $result = $whatsAppService->sendDirectText($contact, 'Relance automatique CRM');

        $this->assertTrue($result);
        $this->assertDatabaseHas('message_log', [
            'contact_id' => $contact->id,
            'channel' => 'whatsapp_openwa',
            'status' => 'sent',
        ]);
    }

    /** @test */
    public function openwa_service_can_create_new_dedicated_session()
    {
        Http::fake([
            'https://mywa.tickets-place.net/api/sessions' => Http::response([
                'id' => 'new-session-uuid-gretinvest',
                'name' => 'gretinvest',
                'status' => 'created',
            ], 201),
        ]);

        $openWaService = new OpenWaService();
        $res = $openWaService->createSession('gretinvest');

        $this->assertTrue($res['success']);
        $this->assertEquals('new-session-uuid-gretinvest', $openWaService->getSessionId());
    }
}
