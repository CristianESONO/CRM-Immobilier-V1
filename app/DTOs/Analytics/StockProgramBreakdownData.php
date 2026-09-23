<?php

namespace App\DTOs\Analytics;

class StockProgramBreakdownData
{
    public function __construct(
        public readonly int $propertyId,
        public readonly string $propertyName,
        public readonly int $totalUnits,
        public readonly int $availableUnits,
        public readonly int $reservedUnits,
        public readonly int $soldUnits,
        public readonly float $sellThroughRate,
        public readonly float $availableValue,
        public readonly float $totalValue,
        public readonly float $weightedPricePerSqm,
        public readonly ?string $drillDownUrl = null,
    ) {}

    public function toArray(): array
    {
        return [
            'property_id' => $this->propertyId,
            'property_name' => $this->propertyName,
            'total_units' => $this->totalUnits,
            'available_units' => $this->availableUnits,
            'reserved_units' => $this->reservedUnits,
            'sold_units' => $this->soldUnits,
            'sell_through_rate' => $this->sellThroughRate,
            'available_value' => $this->availableValue,
            'total_value' => $this->totalValue,
            'weighted_price_per_sqm' => $this->weightedPricePerSqm,
            'drill_down_url' => $this->drillDownUrl,
        ];
    }
}
