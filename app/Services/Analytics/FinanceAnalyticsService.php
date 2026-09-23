<?php

namespace App\Services\Analytics;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\DTOs\Analytics\CashflowForecastData;
use App\DTOs\Analytics\FinanceOverviewData;
use App\DTOs\Analytics\FinancePeriodData;
use App\DTOs\Analytics\OverdueAgingData;
use App\DTOs\Analytics\RefundOverviewData;
use App\Filament\Resources\PaymentScheduleResource;
use App\Filament\Resources\RefundResource;
use App\Filament\Resources\ReservationResource;
use App\Services\Analytics\Kpi\Finance\CashflowKpi;
use App\Services\Analytics\Kpi\Finance\FinanceRevenueKpi;
use App\Services\Analytics\Kpi\Finance\ReceivableKpi;
use App\Services\Analytics\Kpi\Finance\RefundKpi;
use Throwable;

/**
 * V4.3 — Finance Intelligence
 *
 * Lecteur uniquement : ReservationService, PaymentService/ReservationService::recordPayment,
 * RefundService et les state machines restent la source de vérité métier.
 */
class FinanceAnalyticsService
{
    public function __construct(
        protected FinanceRevenueKpi $revenueKpi = new FinanceRevenueKpi(),
        protected ReceivableKpi $receivableKpi = new ReceivableKpi(),
        protected CashflowKpi $cashflowKpi = new CashflowKpi(),
        protected RefundKpi $refundKpi = new RefundKpi(),
    ) {}

    public function overview(?AnalyticsFilterData $filter = null): FinanceOverviewData
    {
        $filter = $filter ?? new AnalyticsFilterData();
        $urls = $this->drillDownUrls();

        $confirmed = $this->revenueKpi->getConfirmedRevenue($filter, applyDateFilter: false);
        $engaged = $this->revenueKpi->getEngagedRevenue($filter, applyDateFilter: false);
        $collected = $this->revenueKpi->getCollectedPortfolio($filter);
        $aging = $this->receivableKpi->getAging($filter);

        return new FinanceOverviewData(
            engagedRevenue: $engaged,
            confirmedRevenue: $confirmed,
            collectedRevenue: $collected,
            remainingBalance: $this->revenueKpi->getRemainingBalance($confirmed, $collected),
            collectionRate: $this->revenueKpi->getPortfolioCollectionRate($collected, $confirmed),
            overdueReservationCount: $aging->overdueReservationCount,
            drillDownUrls: $urls,
        );
    }

    /** Alias conservé pour les widgets déjà branchés. */
    public function getOverview(?AnalyticsFilterData $filter = null): FinanceOverviewData
    {
        return $this->overview($filter);
    }

    public function period(?AnalyticsFilterData $filter = null): FinancePeriodData
    {
        $filter = $filter ?? new AnalyticsFilterData();

        return new FinancePeriodData(
            engagedInPeriod: $this->revenueKpi->getEngagedRevenue($filter, applyDateFilter: true),
            confirmedInPeriod: $this->revenueKpi->getConfirmedRevenue($filter, applyDateFilter: true),
            collectedInPeriod: $this->revenueKpi->getCollectedRevenue($filter, applyDateFilter: true),
            executedRefundsInPeriod: $this->refundKpi->getExecutedInPeriod($filter),
            period: $filter->period,
        );
    }

    public function overdueAging(?AnalyticsFilterData $filter = null): OverdueAgingData
    {
        $filter = $filter ?? new AnalyticsFilterData();
        $aging = $this->receivableKpi->getAging($filter);

        return new OverdueAgingData(
            currentAmount: $aging->currentAmount,
            bucket1to7: $aging->bucket1to7,
            bucket8to30: $aging->bucket8to30,
            bucket31to60: $aging->bucket31to60,
            bucket61to90: $aging->bucket61to90,
            bucketOver90: $aging->bucketOver90,
            openScheduleCount: $aging->openScheduleCount,
            overdueScheduleCount: $aging->overdueScheduleCount,
            overdueAmount: $aging->overdueAmount,
            overdueReservationCount: $aging->overdueReservationCount,
            averageOverdueDays: $aging->averageOverdueDays,
            medianOverdueDays: $aging->medianOverdueDays,
            drillDownUrls: $this->drillDownUrls(),
        );
    }

    public function cashflowForecast(?AnalyticsFilterData $filter = null): CashflowForecastData
    {
        $filter = $filter ?? new AnalyticsFilterData();
        $forecast = $this->cashflowKpi->getForecast($filter);

        return new CashflowForecastData(
            today: $forecast->today,
            next30Days: $forecast->next30Days,
            next60Days: $forecast->next60Days,
            next90Days: $forecast->next90Days,
            drillDownUrls: $this->drillDownUrls(),
        );
    }

    public function getCashflowForecast(?AnalyticsFilterData $filter = null): CashflowForecastData
    {
        return $this->cashflowForecast($filter);
    }

    public function refunds(?AnalyticsFilterData $filter = null): RefundOverviewData
    {
        $filter = $filter ?? new AnalyticsFilterData();
        $gross = $this->revenueKpi->getCollectedPortfolio($filter);
        $data = $this->refundKpi->getOverview($filter, $gross);

        return new RefundOverviewData(
            requestedAmount: $data->requestedAmount,
            approvedAmount: $data->approvedAmount,
            processingAmount: $data->processingAmount,
            executedAmount: $data->executedAmount,
            rejectedAmount: $data->rejectedAmount,
            requestedCount: $data->requestedCount,
            approvedCount: $data->approvedCount,
            processingCount: $data->processingCount,
            executedCount: $data->executedCount,
            rejectedCount: $data->rejectedCount,
            grossCollected: $data->grossCollected,
            netCollected: $data->netCollected,
            drillDownUrls: $this->drillDownUrls(),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function drillDownUrls(): array
    {
        $urls = [
            'reservations' => '/admin/reservations',
            'reservations_confirmed' => '/admin/reservations',
            'schedules' => '/admin/payment-schedules',
            'overdue' => '/admin/payment-schedules?tableFilters[drilldown][value]=overdue',
            'overdue_30_plus' => '/admin/payment-schedules?tableFilters[drilldown][value]=overdue_30_plus',
            'due_within_30_days' => '/admin/payment-schedules?tableFilters[drilldown][value]=due_within_30_days',
            'refunds' => '/admin/refunds',
            'refunds_pending' => '/admin/refunds?tableFilters[status][value]=pending',
            'refunds_approved' => '/admin/refunds?tableFilters[status][value]=approved',
            'refunds_processing' => '/admin/refunds?tableFilters[status][value]=processing',
            'refunds_completed' => '/admin/refunds?tableFilters[status][value]=completed',
            'refunds_rejected' => '/admin/refunds?tableFilters[status][value]=rejected',
        ];

        try {
            if (class_exists(ReservationResource::class)) {
                $urls['reservations'] = ReservationResource::getUrl('index');
                $urls['reservations_confirmed'] = ReservationResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => 'confirmed']],
                ]);
            }
        } catch (Throwable) {
            // fallback already set
        }

        try {
            if (class_exists(PaymentScheduleResource::class)) {
                $urls['schedules'] = PaymentScheduleResource::getUrl('index');
                $urls['overdue'] = PaymentScheduleResource::getUrl('index', [
                    'tableFilters' => ['drilldown' => ['value' => 'overdue']],
                ]);
                $urls['overdue_30_plus'] = PaymentScheduleResource::getUrl('index', [
                    'tableFilters' => ['drilldown' => ['value' => 'overdue_30_plus']],
                ]);
                $urls['due_within_30_days'] = PaymentScheduleResource::getUrl('index', [
                    'tableFilters' => ['drilldown' => ['value' => 'due_within_30_days']],
                ]);
            }
        } catch (Throwable) {
            // fallback already set
        }

        try {
            if (class_exists(RefundResource::class)) {
                $urls['refunds'] = RefundResource::getUrl('index');
                $urls['refunds_pending'] = RefundResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => 'pending']],
                ]);
                $urls['refunds_approved'] = RefundResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => 'approved']],
                ]);
                $urls['refunds_processing'] = RefundResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => 'processing']],
                ]);
                $urls['refunds_completed'] = RefundResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => 'completed']],
                ]);
                $urls['refunds_rejected'] = RefundResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => 'rejected']],
                ]);
            }
        } catch (Throwable) {
            // fallback already set
        }

        return $urls;
    }
}
