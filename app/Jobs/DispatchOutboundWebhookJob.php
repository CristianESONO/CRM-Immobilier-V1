<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DispatchOutboundWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $deliveryId)
    {
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('subscription')->find($this->deliveryId);
        if (!$delivery || !$delivery->subscription) {
            return;
        }

        $subscription = $delivery->subscription;
        $delivery->increment('attempts');

        $jsonPayload = json_encode($delivery->payload);
        $signature = hash_hmac('sha256', $jsonPayload, $subscription->secret);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-CRM-Signature' => $signature,
                'X-CRM-Event' => $delivery->event_type,
                'X-CRM-Delivery' => (string) $delivery->id,
            ])->timeout(10)->post($subscription->url, $delivery->payload);

            if ($response->successful()) {
                $delivery->update([
                    'status' => 'delivered',
                    'status_code' => $response->status(),
                    'response_body' => mb_substr($response->body(), 0, 1000),
                    'next_retry_at' => null,
                ]);
            } else {
                $isLastAttempt = $delivery->attempts >= $this->tries;
                $delivery->update([
                    'status' => 'failed',
                    'status_code' => $response->status(),
                    'response_body' => mb_substr($response->body(), 0, 1000),
                    'next_retry_at' => $isLastAttempt ? null : now()->addMinutes(pow(2, $delivery->attempts)),
                ]);
            }
        } catch (\Throwable $e) {
            $isLastAttempt = $delivery->attempts >= $this->tries;
            $delivery->update([
                'status' => 'failed',
                'status_code' => 500,
                'response_body' => mb_substr($e->getMessage(), 0, 1000),
                'next_retry_at' => $isLastAttempt ? null : now()->addMinutes(pow(2, $delivery->attempts)),
            ]);
        }
    }
}
