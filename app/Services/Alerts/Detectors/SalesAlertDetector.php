<?php

namespace App\Services\Alerts\Detectors;

use App\Models\Contact;
use App\Models\PaymentReminder;
use App\Models\Tenant;
use Carbon\Carbon;

class SalesAlertDetector implements AlertDetectorInterface
{
    public function detect(Tenant $tenant): array
    {
        $alerts = [];
        $now = Carbon::now();

        // 1. Lead sans réponse > SLA (48h)
        Contact::where('tenant_id', $tenant->id)
            ->whereIn('status', ['prospect', 'nouveau'])
            ->whereNull('first_response_at')
            ->where('created_at', '<=', $now->copy()->subHours(48))
            ->each(function (Contact $contact) use ($tenant, &$alerts) {
                $fullName = trim("{$contact->first_name} {$contact->last_name}");

                $alerts[] = [
                    'type' => 'lead_no_response_sla',
                    'severity' => 'warning',
                    'entity_type' => Contact::class,
                    'entity_id' => $contact->id,
                    'assigned_to' => $contact->assigned_to,
                    'title' => "Lead sans réponse > 48h ({$fullName})",
                    'description' => "Le prospect {$fullName} créé le {$contact->created_at->format('d/m/Y H:i')} n'a pas encore reçu de réponse.",
                    'idempotency_key' => "{$tenant->id}:lead_no_response_sla:" . Contact::class . ":{$contact->id}",
                    'metadata' => [
                        'contact_name' => $fullName,
                        'phone' => $contact->phone,
                        'email' => $contact->email,
                        'created_at' => $contact->created_at->toIso8601String(),
                    ],
                ];
            });

        // 2. Action / Rappel échu non traité
        PaymentReminder::where('tenant_id', $tenant->id)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<', $now)
            ->each(function (PaymentReminder $reminder) use ($tenant, &$alerts) {
                $alerts[] = [
                    'type' => 'operational_task_overdue',
                    'severity' => 'warning',
                    'entity_type' => PaymentReminder::class,
                    'entity_id' => $reminder->id,
                    'assigned_to' => $reminder->reservation?->assigned_to,
                    'title' => "Rappel/Action en retard ({$reminder->channel})",
                    'description' => "Le rappel de type {$reminder->trigger_type} sur l'échéance #{$reminder->payment_schedule_id} était prévu le {$reminder->scheduled_at?->format('d/m/Y H:i')}.",
                    'idempotency_key' => "{$tenant->id}:operational_task_overdue:" . PaymentReminder::class . ":{$reminder->id}",
                    'metadata' => [
                        'trigger_type' => $reminder->trigger_type,
                        'scheduled_at' => $reminder->scheduled_at?->toIso8601String(),
                    ],
                ];
            });

        return $alerts;
    }
}
