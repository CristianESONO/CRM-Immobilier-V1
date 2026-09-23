<?php

namespace App\DTOs\Analytics;

/**
 * V4.3 — Flux de période (cohortes temporelles distinctes).
 *
 * Ne pas combiner ces flux en un « taux d'encaissement mensuel » :
 *   collectedInPeriod  → ancré sur payment_date
 *   engagedInPeriod    → ancré sur reserved_at
 *   confirmedInPeriod  → ancré sur reserved_at
 *   executedRefundsInPeriod → ancré sur processed_at
 *
 * Ces populations ne partagent pas la même cohorte.
 */
readonly class FinancePeriodData
{
    public function __construct(
        public float $engagedInPeriod,
        public float $confirmedInPeriod,
        public float $collectedInPeriod,
        public float $executedRefundsInPeriod,
        public string $period = 'all',
    ) {}
}
