<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\MessageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $driver;
    protected string $token;
    protected string $phoneNumberId;
    protected string $apiUrl;
    protected OpenWaService $openWaService;

    public function __construct(OpenWaService $openWaService)
    {
        $this->driver = env('WHATSAPP_DRIVER', 'openwa');
        $this->token = config('services.whatsapp.token', env('WHATSAPP_TOKEN', 'mock_token'));
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', env('WHATSAPP_PHONE_NUMBER_ID', 'mock_phone_number_id'));
        $this->apiUrl = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";
        $this->openWaService = $openWaService;
    }

    /**
     * Send direct text message via OpenWA or Meta driver.
     */
    public function sendDirectText(Contact $contact, string $text, string $template = 'direct_text'): bool
    {
        if ($this->driver === 'openwa') {
            $success = $this->openWaService->sendTextMessage($contact->phone_e164, $text);

            MessageLog::create([
                'tenant_id' => $contact->tenant_id,
                'contact_id' => $contact->id,
                'channel' => 'whatsapp_openwa',
                'template' => $template,
                'status' => $success ? 'sent' : 'failed',
                'sent_at' => now(),
            ]);

            return $success;
        }

        // Meta driver fall-back
        return $this->sendTemplate($contact, $template);
    }

    /**
     * Send WhatsApp template message.
     */
    public function sendTemplate(Contact $contact, string $templateName, string $languageCode = 'fr', array $components = []): bool
    {
        if ($this->driver === 'openwa') {
            // For OpenWA gateway, send template name as text message
            return $this->sendDirectText($contact, "Notification: {$templateName}", $templateName);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $contact->phone_e164,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
                'components' => $components,
            ],
        ];

        try {
            $response = Http::withToken($this->token)
                ->post($this->apiUrl, $payload);

            $status = $response->successful() ? 'sent' : 'failed';
            $responseData = $response->json();
            $providerId = $responseData['messages'][0]['id'] ?? null;
            $errorMsg = $response->failed() ? $response->body() : null;

            MessageLog::create([
                'tenant_id' => $contact->tenant_id,
                'contact_id' => $contact->id,
                'channel' => 'whatsapp_meta',
                'template' => $templateName,
                'provider_id' => $providerId,
                'status' => $status,
                'sent_at' => now(),
                'error' => $errorMsg,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("WhatsApp API Error: " . $e->getMessage());

            MessageLog::create([
                'tenant_id' => $contact->tenant_id,
                'contact_id' => $contact->id,
                'channel' => 'whatsapp_meta',
                'template' => $templateName,
                'status' => 'failed',
                'sent_at' => now(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getOpenWaService(): OpenWaService
    {
        return $this->openWaService;
    }
}
