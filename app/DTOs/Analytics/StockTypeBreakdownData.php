<?php

namespace App\DTOs\Analytics;

class StockTypeBreakdownData
{
    public function __construct(
        public readonly string $typology,
        public readonly int $totalUnits,
        public readonly int $availableUnits,
        public readonly int $reservedUnits,
        public readonly int $soldUnits,
        public readonly float $availableValue,
        public readonly float $availableSurface,
        public readonly float $weightedPricePerSqm,
        public readonly ?string $drillDownUrl = null,
    ) {}

    public function toArray(): array
    {
        return [
            'typology' => $this->typology,
            'total_units' => $this->totalUnits,
            'available_units' => $this->availableUnits,
            'reserved_units' => $this->reservedUnits,
            'sold_units' => $this->soldUnits,
            'available_value' => $this->availableValue,
            'available_surface' => $this->availableSurface,
            'weighted_price_per_sqm' => $this->weightedPricePerSqm,
            'drill_down_url' => $this->drillDownUrl,
        ];
    }
}
