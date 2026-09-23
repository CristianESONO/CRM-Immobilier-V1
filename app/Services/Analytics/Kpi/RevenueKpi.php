<?php

namespace App\Services\Analytics\Kpi;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\Models\Payment;
use App\Models\Reservation;

class RevenueKpi
{
    public function getReservedRevenue(AnalyticsFilterData $filter): float
    {
        $query = Reservation::query()->whereIn('status', ['option', 'confirmed', 'completed']);
        $query = $filter->applyDateFilter($query, 'created_at');

        if ($filter->propertyId) {
            $query->where('property_id', $filter->propertyId);
        }
        if ($filter->assignedTo) {
            $query->where('assigned_to', $filter->assignedTo);
        }

        return (float) $query->sum('total_amount');
    }

    public function getCollectedRevenue(AnalyticsFilterData $filter): float
    {
        $query = Payment::query()->where('status', 'validated');
        $query = $filter->applyDateFilter($query, 'payment_date');

        if ($filter->propertyId || $filter->assignedTo) {
            $query->whereHas('reservation', function ($q) use ($filter) {
                if ($filter->propertyId) {
                    $q->where('property_id', $filter->propertyId);
                }
                if ($filter->assignedTo) {
                    $q->where('assigned_to', $filter->assignedTo);
                }
            });
        }

        return (float) $query->sum('amount');
    }

    public function getRemainingRevenue(float $reserved, float $collected): float
    {
        return max(0.0, $reserved - $collected);
    }

    public function getAverageBasket(float $reserved, int $reservations): float
    {
        if ($reservations <= 0) {
            return 0.0;
        }

        return round($reserved / $reservations, 2);
    }

    public function getCollectionRate(float $collected, float $reserved): float
    {
        if ($reserved <= 0) {
            return 0.0;
        }

        return round(($collected / $reserved) * 100, 1);
    }

    public function getAll(AnalyticsFilterData $filter, int $reservationsCount): array
    {
        $reserved = $this->getReservedRevenue($filter);
        $collected = $this->getCollectedRevenue($filter);
        $remaining = $this->getRemainingRevenue($reserved, $collected);
        $avgBasket = $this->getAverageBasket($reserved, $reservationsCount);
        $rate = $this->getCollectionRate($collected, $reserved);

        return [
            'reserved_revenue' => $reserved,
            'collected_revenue' => $collected,
            'remaining_revenue' => $remaining,
            'average_basket' => $avgBasket,
            'collection_rate' => $rate,
        ];
    }
}
