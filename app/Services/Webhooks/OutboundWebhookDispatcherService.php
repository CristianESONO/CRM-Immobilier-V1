<?php

namespace App\Services\Webhooks;

use App\Jobs\DispatchOutboundWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;

class OutboundWebhookDispatcherService
{
    public function dispatch(string $eventType, int $tenantId, array $payload): int
    {
        $subscriptions = WebhookSubscription::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $queuedCount = 0;

        foreach ($subscriptions as $subscription) {
            if ($subscription->subscribesTo($eventType)) {
                $delivery = WebhookDelivery::create([
                    'tenant_id' => $tenantId,
                    'subscription_id' => $subscription->id,
                    'event_type' => $eventType,
                    'payload' => $payload,
                    'status' => 'pending',
                    'attempts' => 0,
                ]);

                DispatchOutboundWebhookJob::dispatch($delivery->id);
                $queuedCount++;
            }
        }

        return $queuedCount;
    }

    public function replayDelivery(int $deliveryId): bool
    {
        $delivery = WebhookDelivery::find($deliveryId);
        if (!$delivery) {
            return false;
        }

        $delivery->update([
            'status' => 'pending',
            'attempts' => 0,
            'next_retry_at' => null,
        ]);

        DispatchOutboundWebhookJob::dispatch($delivery->id);

        return true;
    }
}
