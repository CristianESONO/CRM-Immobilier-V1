<?php

namespace App\Services\Analytics\Kpi\Finance;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\DTOs\Analytics\CashflowForecastData;
use App\Models\PaymentSchedule;
use Carbon\Carbon;

/**
 * V4.3 — Cashflow prévisionnel (lecture seule).
 *
 * Fenêtres cumulatives sur le reste dû :
 *   today  : due_date ≤ aujourd'hui
 *   J+30   : due_date ≤ aujourd'hui + 30
 *   J+60   : due_date ≤ aujourd'hui + 60
 *   J+90   : due_date ≤ aujourd'hui + 90
 *
 * Les montants déjà encaissés ne sont pas comptés.
 */
class CashflowKpi
{
    public function getForecast(AnalyticsFilterData $filter): CashflowForecastData
    {
        $today = Carbon::today()->startOfDay();
        $end30 = $today->copy()->addDays(30);
        $end60 = $today->copy()->addDays(60);
        $end90 = $today->copy()->addDays(90);

        $todayAmt = 0.0;
        $next30 = 0.0;
        $next60 = 0.0;
        $next90 = 0.0;

        $query = PaymentSchedule::query()
            ->whereColumn('paid_amount', '<', 'expected_amount')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $end90->toDateString());

        FinanceKpiSupport::constrainViaReservation(
            $query,
            $filter,
            FinanceKpiSupport::CONFIRMED_STATUSES,
        );

        $query->each(function (PaymentSchedule $schedule) use (
            $today,
            $end30,
            $end60,
            $end90,
            &$todayAmt,
            &$next30,
            &$next60,
            &$next90,
        ) {
            $remaining = $schedule->remainingAmount();
            if ($remaining <= 0.0) {
                return;
            }

            $due = Carbon::parse($schedule->due_date)->startOfDay();

            if ($due->lte($today)) {
                $todayAmt += $remaining;
            }
            if ($due->lte($end30)) {
                $next30 += $remaining;
            }
            if ($due->lte($end60)) {
                $next60 += $remaining;
            }
            if ($due->lte($end90)) {
                $next90 += $remaining;
            }
        });

        return new CashflowForecastData(
            today: round($todayAmt, 2),
            next30Days: round($next30, 2),
            next60Days: round($next60, 2),
            next90Days: round($next90, 2),
        );
    }
}
