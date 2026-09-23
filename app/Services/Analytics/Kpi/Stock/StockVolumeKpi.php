<?php

namespace App\Services\Analytics\Kpi\Stock;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;

class StockVolumeKpi
{
    protected function baseQuery(AnalyticsFilterData $filter): Builder
    {
        $query = Unit::query();

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

    public function getTotalUnits(AnalyticsFilterData $filter): int
    {
        return $this->baseQuery($filter)->count();
    }

    public function getAvailableUnits(AnalyticsFilterData $filter): int
    {
        return $this->baseQuery($filter)->where('status', 'available')->count();
    }

    public function getReservedUnits(AnalyticsFilterData $filter): int
    {
        return $this->baseQuery($filter)->where('status', 'reserved')->count();
    }

    public function getSoldUnits(AnalyticsFilterData $filter): int
    {
        return $this->baseQuery($filter)->where('status', 'sold')->count();
    }

    /**
     * Taux d'écoulement commercial : vendus / (disponibles + réservés + vendus).
     */
    public function getSellThroughRate(int $sold, int $commercialTotal): float
    {
        if ($commercialTotal <= 0) {
            return 0.0;
        }

        return round(($sold / $commercialTotal) * 100, 1);
    }

    public function getReservationRate(int $reserved, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($reserved / $total) * 100, 1);
    }

    public function getAvailabilityRate(int $available, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($available / $total) * 100, 1);
    }
}
