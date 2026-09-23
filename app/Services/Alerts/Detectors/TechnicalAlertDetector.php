<?php

namespace App\Services\Alerts\Detectors;

use App\Models\SignatureWebhookEvent;
use App\Models\Tenant;

class TechnicalAlertDetector implements AlertDetectorInterface
{
    public function detect(Tenant $tenant): array
    {
        $alerts = [];

        SignatureWebhookEvent::where('tenant_id', $tenant->id)
            ->where('status', 'failed')
            ->each(function (SignatureWebhookEvent $event) use ($tenant, &$alerts) {
                $alerts[] = [
                    'type' => 'technical_webhook_error',
                    'severity' => 'critical',
                    'entity_type' => SignatureWebhookEvent::class,
                    'entity_id' => $event->id,
                    'assigned_to' => null,
                    'title' => "Échec webhook signature ({$event->provider})",
                    'description' => "Le webhook {$event->event_type} (ID {$event->event_id}) a échoué.",
                    'idempotency_key' => "{$tenant->id}:technical_webhook_error:" . SignatureWebhookEvent::class . ":{$event->id}",
                    'metadata' => [
                        'provider' => $event->provider,
                        'event_id' => $event->event_id,
                        'event_type' => $event->event_type,
                        'signature_request_id' => $event->signature_request_id,
                    ],
                ];
            });

        return $alerts;
    }
}
