<?php

namespace App\Services\Analytics\Kpi\Stock;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\DTOs\Analytics\StockAgingData;
use App\Models\Unit;
use Carbon\Carbon;

class StockAgingKpi
{
    public function getAging(AnalyticsFilterData $filter): StockAgingData
    {
        $query = Unit::query()->where('status', 'available');

        if ($filter->propertyId) {
            $query->where('property_id', $filter->propertyId);
        }

        if ($filter->unitType) {
            $query->where('typology', $filter->unitType);
        }

        $units = $query->select(['id', 'marketed_at', 'created_at'])->get();

        if ($units->isEmpty()) {
            return new StockAgingData(
                bucket0to30: 0,
                bucket31to90: 0,
                bucket91to180: 0,
                bucket181to365: 0,
                bucketOver365: 0,
                averageDays: 0.0,
            );
        }

        $b0_30 = 0;
        $b31_90 = 0;
        $b91_180 = 0;
        $b181_365 = 0;
        $bOver365 = 0;
        $totalDays = 0;
        $now = Carbon::now();

        foreach ($units as $unit) {
            $date = $unit->marketed_at ?? $unit->created_at ?? $now;
            $ageDays = max(0, (int) Carbon::parse($date)->diffInDays($now));
            $totalDays += $ageDays;

            if ($ageDays <= 30) {
                $b0_30++;
            } elseif ($ageDays <= 90) {
                $b31_90++;
            } elseif ($ageDays <= 180) {
                $b91_180++;
            } elseif ($ageDays <= 365) {
                $b181_365++;
            } else {
                $bOver365++;
            }
        }

        $avg = round($totalDays / $units->count(), 1);

        return new StockAgingData(
            bucket0to30: $b0_30,
            bucket31to90: $b31_90,
            bucket91to180: $b91_180,
            bucket181to365: $b181_365,
            bucketOver365: $bOver365,
            averageDays: $avg,
        );
    }
}
