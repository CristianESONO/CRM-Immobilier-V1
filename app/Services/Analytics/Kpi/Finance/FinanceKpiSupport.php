<?php

namespace App\Services\Analytics\Kpi\Finance;

use App\DTOs\Analytics\AnalyticsFilterData;
use Illuminate\Database\Eloquent\Builder;

final class FinanceKpiSupport
{
    public const ENGAGED_STATUSES = ['option', 'confirmed', 'completed'];

    public const CONFIRMED_STATUSES = ['confirmed', 'completed'];

    public const VALIDATED_PAYMENT = 'validated';

    public static function constrainReservations(
        Builder $query,
        AnalyticsFilterData $filter,
        array $statuses,
        bool $applyDateFilter = false,
        string $dateColumn = 'reserved_at',
    ): Builder {
        $query->whereIn('status', $statuses);

        if ($applyDateFilter) {
            $query = $filter->applyDateFilter($query, $dateColumn);
        }

        if ($filter->propertyId) {
            $query->where('property_id', $filter->propertyId);
        }

        if ($filter->assignedTo) {
            $query->where('assigned_to', $filter->assignedTo);
        }

        return $query;
    }

    public static function constrainViaReservation(
        Builder $query,
        AnalyticsFilterData $filter,
        array $statuses,
        bool $applyReservationDateFilter = false,
    ): Builder {
        return $query->whereHas('reservation', function (Builder $q) use ($filter, $statuses, $applyReservationDateFilter) {
            self::constrainReservations($q, $filter, $statuses, $applyReservationDateFilter);
        });
    }

    public static function remaining(float $expected, float $paid): float
    {
        return max(0.0, $expected - $paid);
    }

    public static function collectionRate(float $collected, float $contractual): float
    {
        if ($contractual <= 0.0) {
            return 0.0;
        }

        return round(($collected / $contractual) * 100, 1);
    }

    /**
     * @param  list<int|float>  $values
     */
    public static function median(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        sort($values);
        $mid = intdiv($count, 2);

        if ($count % 2 === 1) {
            return round((float) $values[$mid], 1);
        }

        return round(((float) $values[$mid - 1] + (float) $values[$mid]) / 2, 1);
    }
}
