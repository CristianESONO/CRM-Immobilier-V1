<?php

namespace App\DTOs\Analytics;

class SalesOverviewData
{
    public function __construct(
        // Volumes
        public readonly int $totalProspects,
        public readonly int $qualifiedProspects,
        public readonly int $totalOpportunities,
        public readonly int $totalReservations,
        public readonly int $totalSignedContracts,

        // Financier
        public readonly float $reservedRevenue,
        public readonly float $collectedRevenue,
        public readonly float $remainingRevenue,
        public readonly float $averageBasket,
        public readonly float $collectionRate,

        // Taux de conversion
        public readonly float $qualificationRate,
        public readonly float $opportunityToReservationRate,
        public readonly float $reservationToSignatureRate,
        public readonly float $globalConversionRate,

        // Vélocité
        public readonly float $averageFirstResponseHours,
        public readonly float $averageLeadToQualifiedDays,
        public readonly float $averageQualifiedToReservationDays,
        public readonly float $averageReservationToSignedDays,

        // Liens de Drill-down
        public readonly array $drillDownUrls = [],
    ) {}
}
