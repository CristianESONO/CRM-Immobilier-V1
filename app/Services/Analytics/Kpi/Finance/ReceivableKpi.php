<?php

namespace App\Services\Analytics\Kpi\Finance;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\DTOs\Analytics\OverdueAgingData;
use App\Models\PaymentSchedule;
use Carbon\Carbon;

/**
 * V4.3 — Créances (lecture seule).
 *
 * Date de référence : due_date.
 * Retard            : aujourd'hui − due_date.
 * Montant           : reste dû (paiements partiels exclus du brut).
 */
class ReceivableKpi
{
    public function getAging(AnalyticsFilterData $filter): OverdueAgingData
    {
        $today = Carbon::today()->startOfDay();

        $current = 0.0;
        $b1_7 = 0.0;
        $b8_30 = 0.0;
        $b31_60 = 0.0;
        $b61_90 = 0.0;
        $bOver90 = 0.0;
        $openCount = 0;
        $overdueCount = 0;
        $overdueAmount = 0.0;
        $overdueReservationIds = [];
        $overdueDays = [];

        $this->openSchedulesQuery($filter)
            ->whereNotNull('due_date')
            ->each(function (PaymentSchedule $schedule) use (
                $today,
                &$current,
                &$b1_7,
                &$b8_30,
                &$b31_60,
                &$b61_90,
                &$bOver90,
                &$openCount,
                &$overdueCount,
                &$overdueAmount,
                &$overdueReservationIds,
                &$overdueDays,
            ) {
                $remaining = $schedule->remainingAmount();
                if ($remaining <= 0.0) {
                    return;
                }

                $openCount++;
                $due = Carbon::parse($schedule->due_date)->startOfDay();

                if ($due->gte($today)) {
                    $current += $remaining;

                    return;
                }

                $days = (int) $due->diffInDays($today);
                $overdueCount++;
                $overdueAmount += $remaining;
                $overdueDays[] = $days;
                $overdueReservationIds[$schedule->reservation_id] = true;

                if ($days <= 7) {
                    $b1_7 += $remaining;
                } elseif ($days <= 30) {
                    $b8_30 += $remaining;
                } elseif ($days <= 60) {
                    $b31_60 += $remaining;
                } elseif ($days <= 90) {
                    $b61_90 += $remaining;
                } else {
                    $bOver90 += $remaining;
                }
            });

        $avg = count($overdueDays) > 0
            ? round(array_sum($overdueDays) / count($overdueDays), 1)
            : 0.0;

        return new OverdueAgingData(
            currentAmount: $current,
            bucket1to7: $b1_7,
            bucket8to30: $b8_30,
            bucket31to60: $b31_60,
            bucket61to90: $b61_90,
            bucketOver90: $bOver90,
            openScheduleCount: $openCount,
            overdueScheduleCount: $overdueCount,
            overdueAmount: round($overdueAmount, 2),
            overdueReservationCount: count($overdueReservationIds),
            averageOverdueDays: $avg,
            medianOverdueDays: FinanceKpiSupport::median($overdueDays),
        );
    }

    protected function openSchedulesQuery(AnalyticsFilterData $filter)
    {
        $query = PaymentSchedule::query()->whereColumn('paid_amount', '<', 'expected_amount');

        return FinanceKpiSupport::constrainViaReservation(
            $query,
            $filter,
            FinanceKpiSupport::CONFIRMED_STATUSES,
        );
    }
}
