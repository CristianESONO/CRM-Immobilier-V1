<?php

namespace App\Services\Alerts\Detectors;

use App\Models\Reservation;
use App\Models\Tenant;

class StockAlertDetector implements AlertDetectorInterface
{
    public function detect(Tenant $tenant): array
    {
        $alerts = [];

        // Lot réservé en statut 'option' depuis > 7 jours sans passer à 'confirmed'
        Reservation::where('tenant_id', $tenant->id)
            ->where('status', 'option')
            ->where('reserved_at', '<=', now()->subDays(7))
            ->with(['unit', 'property'])
            ->each(function (Reservation $reservation) use ($tenant, &$alerts) {
                $unitRef = $reservation->unit?->reference ?? "Lot #{$reservation->unit_id}";
                $propertyName = $reservation->property?->name ?? 'Programme';

                $alerts[] = [
                    'type' => 'stock_option_expired',
                    'severity' => 'warning',
                    'entity_type' => Reservation::class,
                    'entity_id' => $reservation->id,
                    'assigned_to' => $reservation->assigned_to,
                    'title' => "Option expirée sur {$unitRef}",
                    'description' => "Le lot {$unitRef} ({$propertyName}) est bloqué en option depuis plus de 7 jours (réservation {$reservation->reference}).",
                    'idempotency_key' => "{$tenant->id}:stock_option_expired:" . Reservation::class . ":{$reservation->id}",
                    'metadata' => [
                        'unit_id' => $reservation->unit_id,
                        'unit_reference' => $unitRef,
                        'property_name' => $propertyName,
                        'reserved_at' => $reservation->reserved_at?->toIso8601String(),
                    ],
                ];
            });

        return $alerts;
    }
}
