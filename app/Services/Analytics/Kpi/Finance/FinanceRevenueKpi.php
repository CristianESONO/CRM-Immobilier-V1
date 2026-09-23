<?php

namespace App\Services\Analytics\Kpi\Finance;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Reservation;

/**
 * V4.3 — Engagement commercial (lecture seule).
 *
 * CA engagé   : reserved_at, statuts option + confirmed + completed
 * CA confirmé : reserved_at, statuts confirmed + completed
 * CA encaissé : payment_date, payments.status = validated uniquement
 */
class FinanceRevenueKpi
{
    public function getEngagedRevenue(AnalyticsFilterData $filter, bool $applyDateFilter = true): float
    {
        $query = FinanceKpiSupport::constrainReservations(
            Reservation::query(),
            $filter,
            FinanceKpiSupport::ENGAGED_STATUSES,
            $applyDateFilter,
        );

        return (float) $query->sum('total_amount');
    }

    public function getConfirmedRevenue(AnalyticsFilterData $filter, bool $applyDateFilter = true): float
    {
        $query = FinanceKpiSupport::constrainReservations(
            Reservation::query(),
            $filter,
            FinanceKpiSupport::CONFIRMED_STATUSES,
            $applyDateFilter,
        );

        return (float) $query->sum('total_amount');
    }

    /**
     * Encaissement de période (ou tous temps si le filtre n'a pas de dates).
     * Ancré sur payment_date. Ignore pending / rejected.
     */
    public function getCollectedRevenue(AnalyticsFilterData $filter, bool $applyDateFilter = true): float
    {
        $query = Payment::query()->where('status', FinanceKpiSupport::VALIDATED_PAYMENT);

        FinanceKpiSupport::constrainViaReservation(
            $query,
            $filter,
            FinanceKpiSupport::CONFIRMED_STATUSES,
        );

        if ($applyDateFilter) {
            $query = $filter->applyDateFilter($query, 'payment_date');
        }

        return (float) $query->sum('amount');
    }

    /**
     * Encaissement du portefeuille : tous les payments validés, sans filtre de période.
     */
    public function getCollectedPortfolio(AnalyticsFilterData $filter): float
    {
        return $this->getCollectedRevenue($filter, applyDateFilter: false);
    }

    public function getRemainingBalance(float $confirmedRevenue, float $collectedPortfolio): float
    {
        return max(0.0, $confirmedRevenue - $collectedPortfolio);
    }

    /**
     * Taux d'encaissement du portefeuille — ne pas utiliser collectedInPeriod au numérateur.
     */
    public function getPortfolioCollectionRate(float $collectedPortfolio, float $confirmedRevenue): float
    {
        return FinanceKpiSupport::collectionRate($collectedPortfolio, $confirmedRevenue);
    }

    /**
     * Reste dû agrégé sur les échéances (paiements partiels inclus).
     */
    public function getScheduleRemaining(AnalyticsFilterData $filter): float
    {
        $total = 0.0;

        $this->openSchedulesQuery($filter)->each(function (PaymentSchedule $schedule) use (&$total) {
            $total += $schedule->remainingAmount();
        });

        return round($total, 2);
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
