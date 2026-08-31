<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenWaService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $sessionId;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.openwa.url', env('OPENWA_API_URL', 'https://mywa.tickets-place.net/api')), '/');
        $this->apiKey = config('services.openwa.key', env('OPENWA_API_KEY', 'owa_k1_e05c74e13e2a679eae14d957458d798979f5a780c3fe36e76284969fd8c3c4b0'));
        $this->sessionId = config('services.openwa.session_id', env('OPENWA_SESSION_ID', '1b9201d2-932d-4cae-8b5f-c58c1d9780a1'));
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Create a brand new dedicated WhatsApp session on OpenWA gateway.
     * Example: $name = "gretinvest"
     */
    public function createSession(string $name, array $config = ['autoReconnect' => true]): array
    {
        $url = "{$this->baseUrl}/sessions";

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->post($url, [
                    'name' => $name,
                    'config' => $config,
                ]);

            $data = $response->json() ?? [];

            if ($response->successful() && isset($data['id'])) {
                $this->sessionId = $data['id'];
                Log::info("OpenWA session created successfully: {$name} (ID: {$data['id']})");
            }

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error("OpenWA createSession Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send direct text message via OpenWA session.
     */
    public function sendTextMessage(string $phone, string $text): bool
    {
        $chatId = $this->formatChatId($phone);

        $url = "{$this->baseUrl}/sessions/{$this->sessionId}/messages/send-text";

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->post($url, [
                    'chatId' => $chatId,
                    'text' => $text,
                ]);

            if ($response->successful()) {
                Log::info("OpenWA message sent to {$chatId}");
                return true;
            }

            Log::error("OpenWA send-text failed ({$response->status()}): " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("OpenWA Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current status of the session.
     */
    public function getSessionStatus(): array
    {
        $url = "{$this->baseUrl}/sessions/{$this->sessionId}";

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            return ['status' => 'unknown', 'error' => $response->body()];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Get QR code for authentication if status is qr_ready.
     */
    public function getQrCode(): ?string
    {
        $url = "{$this->baseUrl}/sessions/{$this->sessionId}/qr";

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return $data['qrCode'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Start the OpenWA session.
     */
    public function startSession(): array
    {
        $url = "{$this->baseUrl}/sessions/{$this->sessionId}/start";

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->post($url);

            return $response->json() ?? ['status' => 'started'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Check if a phone number exists on WhatsApp.
     */
    public function checkContact(string $phone): array
    {
        $chatId = $this->formatChatId($phone);
        $url = "{$this->baseUrl}/sessions/{$this->sessionId}/contacts/check/{$chatId}";

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->get($url);

            return $response->json() ?? [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Format phone number to clean digits (e.g. +221785962662 -> 221785962662).
     */
    protected function formatChatId(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
