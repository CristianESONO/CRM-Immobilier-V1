<?php

namespace App\DTOs\Analytics;

/**
 * V4.3 — Remboursements.
 *
 * Dates de référence :
 *   demandés  → requested_at / status=pending
 *   approuvés → approved_at  / status=approved
 *   traitement→ status=processing
 *   exécutés  → processed_at / status=completed (seule sortie de trésorerie)
 *   rejetés   → status=rejected
 *
 * Encaissement net = encaissement brut (payments validés) − remboursements exécutés.
 * Un refund pending / approved / processing n'est pas une sortie de trésorerie.
 */
readonly class RefundOverviewData
{
    public function __construct(
        public float $requestedAmount,
        public float $approvedAmount,
        public float $processingAmount,
        public float $executedAmount,
        public float $rejectedAmount,
        public int $requestedCount,
        public int $approvedCount,
        public int $processingCount,
        public int $executedCount,
        public int $rejectedCount,
        public float $grossCollected,
        public float $netCollected,
        public array $drillDownUrls = [],
    ) {}

    public function pendingOperationalAmount(): float
    {
        return $this->requestedAmount + $this->approvedAmount + $this->processingAmount;
    }

    public function pendingOperationalCount(): int
    {
        return $this->requestedCount + $this->approvedCount + $this->processingCount;
    }
}
