<?php

namespace App\Services\Analytics\Kpi\Stock;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;

class StockPricingKpi
{
    protected function baseQuery(AnalyticsFilterData $filter, ?string $status = null): Builder
    {
        $query = Unit::query();

        if ($status) {
            $query->where('status', $status);
        }

        if ($filter->propertyId) {
            $query->where('property_id', $filter->propertyId);
        }

        if ($filter->unitType) {
            $query->where('typology', $filter->unitType);
        }

        if ($filter->period !== 'all') {
            $query = $filter->applyDateFilter($query, 'created_at');
        }

        return $query;
    }

    public function getStockValue(AnalyticsFilterData $filter, ?string $status = null): float
    {
        return (float) $this->baseQuery($filter, $status)->sum('price');
    }

    public function getTotalSurface(AnalyticsFilterData $filter, ?string $status = null): float
    {
        return (float) $this->baseQuery($filter, $status)->sum('area');
    }

    /**
     * Prix au m² pondéré : SUM(price) / SUM(area) pour les lots avec surface > 0.
     */
    public function getWeightedPricePerSqm(AnalyticsFilterData $filter, ?string $status = null): float
    {
        $query = $this->baseQuery($filter, $status)->where('area', '>', 0);

        $totalPrice = (float) $query->sum('price');
        $totalArea = (float) $query->sum('area');

        if ($totalArea <= 0.0) {
            return 0.0;
        }

        return round($totalPrice / $totalArea, 2);
    }

    /**
     * Surface moyenne par lot (m²).
     */
    public function getAverageSurface(AnalyticsFilterData $filter): float
    {
        $avg = $this->baseQuery($filter)->where('area', '>', 0)->avg('area');

        return round((float) ($avg ?? 0.0), 1);
    }
}
