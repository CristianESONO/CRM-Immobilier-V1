<?php

namespace App\Services\Alerts\Detectors;

use App\Models\BuyerDocument;
use App\Models\Reservation;
use App\Models\Tenant;
use Carbon\Carbon;

class KycAlertDetector implements AlertDetectorInterface
{
    public function detect(Tenant $tenant): array
    {
        $alerts = [];

        // 1. Document expiré ou à statut expired
        BuyerDocument::where('tenant_id', $tenant->id)
            ->where(function ($q) {
                $q->where('status', 'expired')
                  ->orWhere(function ($sub) {
                      $sub->whereNotNull('expires_at')
                          ->whereDate('expires_at', '<', Carbon::today()->toDateString());
                  });
            })
            ->each(function (BuyerDocument $doc) use ($tenant, &$alerts) {
                $alerts[] = [
                    'type' => 'kyc_document_expired',
                    'severity' => 'critical',
                    'entity_type' => BuyerDocument::class,
                    'entity_id' => $doc->id,
                    'assigned_to' => $doc->reservation?->assigned_to ?? $doc->contact?->assigned_to,
                    'title' => "Document KYC expiré ({$doc->title})",
                    'description' => "Le document {$doc->document_type} pour le contact #{$doc->contact_id} a expiré.",
                    'idempotency_key' => "{$tenant->id}:kyc_document_expired:" . BuyerDocument::class . ":{$doc->id}",
                    'metadata' => [
                        'document_type' => $doc->document_type,
                        'contact_id' => $doc->contact_id,
                        'reservation_id' => $doc->reservation_id,
                        'expires_at' => $doc->expires_at?->toDateString(),
                    ],
                ];
            });

        // 2. Dossier KYC incomplet sur réservation confirmée
        Reservation::where('tenant_id', $tenant->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->with(['buyerDocuments'])
            ->each(function (Reservation $reservation) use ($tenant, &$alerts) {
                $docs = $reservation->buyerDocuments;
                $hasMissingOrRejected = $docs->contains(fn ($d) => in_array($d->status, ['missing', 'rejected', 'expired'], true));
                $hasNoDocs = $docs->isEmpty();

                if ($hasMissingOrRejected || $hasNoDocs) {
                    $alerts[] = [
                        'type' => 'kyc_incomplete_folder',
                        'severity' => 'warning',
                        'entity_type' => Reservation::class,
                        'entity_id' => $reservation->id,
                        'assigned_to' => $reservation->assigned_to,
                        'title' => "Dossier KYC incomplet sur la réservation {$reservation->reference}",
                        'description' => "La réservation {$reservation->reference} requiert des documents KYC validés.",
                        'idempotency_key' => "{$tenant->id}:kyc_incomplete_folder:" . Reservation::class . ":{$reservation->id}",
                        'metadata' => [
                            'reservation_reference' => $reservation->reference,
                            'contact_id' => $reservation->contact_id,
                            'total_documents' => $docs->count(),
                        ],
                    ];
                }
            });

        return $alerts;
    }
}
