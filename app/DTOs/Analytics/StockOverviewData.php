<?php

namespace App\DTOs\Analytics;

class StockOverviewData
{
    public function __construct(
        // Volumes
        public readonly int $totalUnits,
        public readonly int $availableUnits,
        public readonly int $reservedUnits,
        public readonly int $soldUnits,

        // Taux (%)
        public readonly float $sellThroughRate,
        public readonly float $reservationRate,
        public readonly float $availabilityRate,

        // Valeurs financières (FCFA)
        public readonly float $totalStockValue,
        public readonly float $availableStockValue,
        public readonly float $reservedStockValue,
        public readonly float $soldStockValue,

        // Surfaces (m²)
        public readonly float $totalSurface,
        public readonly float $availableSurface,
        public readonly float $averageSurface,

        // Prix / m² pondérés (FCFA/m²)
        public readonly float $weightedPricePerSqm,
        public readonly float $availableWeightedPricePerSqm,
        public readonly float $reservedWeightedPricePerSqm,
        public readonly float $soldWeightedPricePerSqm,

        // Ancienneté
        public readonly float $averageAgingDays,

        // Drill-down URLs
        public readonly array $drillDownUrls = [],
    ) {}
}
