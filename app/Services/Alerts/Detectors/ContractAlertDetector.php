<?php

namespace App\Services\Alerts\Detectors;

use App\Models\Contract;
use App\Models\Reservation;
use App\Models\Tenant;
use Carbon\Carbon;

class ContractAlertDetector implements AlertDetectorInterface
{
    public function detect(Tenant $tenant): array
    {
        $alerts = [];
        $now = Carbon::now();

        // 1. Contrat en attente de validation (> 24h)
        Contract::where('tenant_id', $tenant->id)
            ->where('status', 'pending_approval')
            ->where('created_at', '<=', $now->copy()->subHours(24))
            ->each(function (Contract $contract) use ($tenant, &$alerts) {
                $alerts[] = [
                    'type' => 'contract_pending_approval',
                    'severity' => 'warning',
                    'entity_type' => Contract::class,
                    'entity_id' => $contract->id,
                    'assigned_to' => $contract->reservation?->assigned_to,
                    'title' => "Contrat #{$contract->contract_number} en attente de validation",
                    'description' => "Le contrat {$contract->contract_number} attend validation administrative depuis plus de 24h.",
                    'idempotency_key' => "{$tenant->id}:contract_pending_approval:" . Contract::class . ":{$contract->id}",
                    'metadata' => [
                        'contract_number' => $contract->contract_number,
                        'reservation_id' => $contract->reservation_id,
                        'created_at' => $contract->created_at->toIso8601String(),
                    ],
                ];
            });

        // 2. Contrat envoyé non signé depuis > 7 jours
        Contract::where('tenant_id', $tenant->id)
            ->where('status', 'sent')
            ->where('updated_at', '<=', $now->copy()->subDays(7))
            ->each(function (Contract $contract) use ($tenant, &$alerts) {
                $alerts[] = [
                    'type' => 'signature_sent_unsigned',
                    'severity' => 'critical',
                    'entity_type' => Contract::class,
                    'entity_id' => $contract->id,
                    'assigned_to' => $contract->reservation?->assigned_to,
                    'title' => "Contrat #{$contract->contract_number} envoyé non signé (> 7j)",
                    'description' => "La demande de signature pour le contrat {$contract->contract_number} n'a pas abouti depuis 7 jours.",
                    'idempotency_key' => "{$tenant->id}:signature_sent_unsigned:" . Contract::class . ":{$contract->id}",
                    'metadata' => [
                        'contract_number' => $contract->contract_number,
                        'signature_request_id' => $contract->signature_request_id,
                        'sent_at' => $contract->updated_at->toIso8601String(),
                    ],
                ];
            });

        // 3. Réservation confirmée ou en option sans contrat créé depuis > 3 jours
        Reservation::where('tenant_id', $tenant->id)
            ->whereIn('status', ['confirmed', 'option'])
            ->whereDoesntHave('contract')
            ->where('created_at', '<=', $now->copy()->subDays(3))
            ->each(function (Reservation $reservation) use ($tenant, &$alerts) {
                $alerts[] = [
                    'type' => 'reservation_without_contract',
                    'severity' => 'warning',
                    'entity_type' => Reservation::class,
                    'entity_id' => $reservation->id,
                    'assigned_to' => $reservation->assigned_to,
                    'title' => "Réservation {$reservation->reference} sans contrat",
                    'description' => "La réservation {$reservation->reference} existe depuis plus de 3 jours sans contrat initié.",
                    'idempotency_key' => "{$tenant->id}:reservation_without_contract:" . Reservation::class . ":{$reservation->id}",
                    'metadata' => [
                        'reservation_reference' => $reservation->reference,
                        'status' => $reservation->status,
                        'created_at' => $reservation->created_at->toIso8601String(),
                    ],
                ];
            });

        return $alerts;
    }
}
