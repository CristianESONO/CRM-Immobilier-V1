<?php

namespace App\Services\Analytics;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\DTOs\Analytics\FunnelData;
use App\DTOs\Analytics\FunnelStepData;
use App\DTOs\Analytics\SalesOverviewData;
use App\Filament\Resources\ContactResource;
use App\Filament\Resources\ContractResource;
use App\Filament\Resources\OpportunityResource;
use App\Filament\Resources\ReservationResource;
use App\Services\Analytics\Kpi\ConversionKpi;
use App\Services\Analytics\Kpi\PipelineKpi;
use App\Services\Analytics\Kpi\RevenueKpi;
use App\Services\Analytics\Kpi\VelocityKpi;
use Throwable;

class SalesAnalyticsService
{
    public function __construct(
        protected PipelineKpi $pipelineKpi = new PipelineKpi(),
        protected ConversionKpi $conversionKpi = new ConversionKpi(),
        protected RevenueKpi $revenueKpi = new RevenueKpi(),
        protected VelocityKpi $velocityKpi = new VelocityKpi(),
    ) {}

    public function getOverview(?AnalyticsFilterData $filter = null): SalesOverviewData
    {
        $filter = $filter ?? new AnalyticsFilterData();

        // 1. Pipeline volumes
        $pipeline = $this->pipelineKpi->getAll($filter);
        $totalProspects = $pipeline['prospects'];
        $qualifiedProspects = $pipeline['qualifies'];
        $totalOpportunities = $pipeline['opportunites'];
        $totalReservations = $pipeline['reservations'];
        $totalSignedContracts = $pipeline['signatures'];

        // 2. Financial metrics
        $financial = $this->revenueKpi->getAll($filter, $totalReservations);

        // 3. Conversion rates
        $qualificationRate = $this->conversionKpi->getQualificationRate($qualifiedProspects, $totalProspects);
        $oppToResaRate = $this->conversionKpi->getOpportunityToReservationRate($totalReservations, $totalOpportunities);
        $resaToSignRate = $this->conversionKpi->getReservationToSignatureRate($totalSignedContracts, $totalReservations);
        $globalConversionRate = $this->conversionKpi->getGlobalConversionRate($totalSignedContracts, $totalProspects);

        // 4. Velocities
        $velocities = $this->velocityKpi->getAll($filter);

        // 5. Drill-down URLs
        $urls = $this->getDrillDownUrls();

        return new SalesOverviewData(
            totalProspects: $totalProspects,
            qualifiedProspects: $qualifiedProspects,
            totalOpportunities: $totalOpportunities,
            totalReservations: $totalReservations,
            totalSignedContracts: $totalSignedContracts,
            reservedRevenue: $financial['reserved_revenue'],
            collectedRevenue: $financial['collected_revenue'],
            remainingRevenue: $financial['remaining_revenue'],
            averageBasket: $financial['average_basket'],
            collectionRate: $financial['collection_rate'],
            qualificationRate: $qualificationRate,
            opportunityToReservationRate: $oppToResaRate,
            reservationToSignatureRate: $resaToSignRate,
            globalConversionRate: $globalConversionRate,
            averageFirstResponseHours: $velocities['average_first_response_hours'],
            averageLeadToQualifiedDays: $velocities['average_lead_to_qualified_days'],
            averageQualifiedToReservationDays: $velocities['average_qualified_to_reservation_days'],
            averageReservationToSignedDays: $velocities['average_reservation_to_signed_days'],
            drillDownUrls: $urls,
        );
    }

    public function getFunnel(?AnalyticsFilterData $filter = null): FunnelData
    {
        $filter = $filter ?? new AnalyticsFilterData();
        $volumes = $this->pipelineKpi->getAll($filter);
        $urls = $this->getDrillDownUrls();

        $prospects = $volumes['prospects'];
        $qualifies = $volumes['qualifies'];
        $opportunites = $volumes['opportunites'];
        $visites = $volumes['visites'];
        $offres = $volumes['offres'];
        $reservations = $volumes['reservations'];
        $signatures = $volumes['signatures'];

        $steps = [
            new FunnelStepData(
                key: 'prospects',
                label: 'Prospects Entrants',
                count: $prospects,
                stepConversionRate: 100.0,
                globalConversionRate: 100.0,
                color: '#3b82f6', // Blue
                drillDownUrl: $urls['contacts'] ?? null,
            ),
            new FunnelStepData(
                key: 'qualifies',
                label: 'Prospects Qualifiés',
                count: $qualifies,
                stepConversionRate: $this->conversionKpi->safeRate($qualifies, $prospects),
                globalConversionRate: $this->conversionKpi->safeRate($qualifies, $prospects),
                color: '#06b6d4', // Cyan
                drillDownUrl: $urls['contacts_qualified'] ?? null,
            ),
            new FunnelStepData(
                key: 'opportunites',
                label: 'Opportunités Ouvertes',
                count: $opportunites,
                stepConversionRate: $this->conversionKpi->safeRate($opportunites, $qualifies),
                globalConversionRate: $this->conversionKpi->safeRate($opportunites, $prospects),
                color: '#6366f1', // Indigo
                drillDownUrl: $urls['opportunities'] ?? null,
            ),
            new FunnelStepData(
                key: 'visites',
                label: 'Visites Réalisées',
                count: $visites,
                stepConversionRate: $this->conversionKpi->safeRate($visites, $opportunites),
                globalConversionRate: $this->conversionKpi->safeRate($visites, $prospects),
                color: '#8b5cf6', // Purple
                drillDownUrl: $urls['opportunities'] ?? null,
            ),
            new FunnelStepData(
                key: 'offres',
                label: 'Offres Émises',
                count: $offres,
                stepConversionRate: $this->conversionKpi->safeRate($offres, $visites),
                globalConversionRate: $this->conversionKpi->safeRate($offres, $prospects),
                color: '#f59e0b', // Amber
                drillDownUrl: $urls['opportunities'] ?? null,
            ),
            new FunnelStepData(
                key: 'reservations',
                label: 'Contrats Réservation',
                count: $reservations,
                stepConversionRate: $this->conversionKpi->safeRate($reservations, $offres),
                globalConversionRate: $this->conversionKpi->safeRate($reservations, $prospects),
                color: '#10b981', // Emerald
                drillDownUrl: $urls['reservations'] ?? null,
            ),
            new FunnelStepData(
                key: 'signatures',
                label: 'Contrats Signés',
                count: $signatures,
                stepConversionRate: $this->conversionKpi->safeRate($signatures, $reservations),
                globalConversionRate: $this->conversionKpi->safeRate($signatures, $prospects),
                color: '#059669', // Dark emerald
                drillDownUrl: $urls['contracts'] ?? null,
            ),
        ];

        return new FunnelData(
            steps: $steps,
            totalProspects: $prospects,
            totalSigned: $signatures,
            overallConversionRate: $this->conversionKpi->safeRate($signatures, $prospects),
        );
    }

    public function getConversionMetrics(?AnalyticsFilterData $filter = null): array
    {
        $filter = $filter ?? new AnalyticsFilterData();
        $volumes = $this->pipelineKpi->getAll($filter);

        return $this->conversionKpi->calculateFunnelConversions($volumes);
    }

    public function getVelocityMetrics(?AnalyticsFilterData $filter = null): array
    {
        $filter = $filter ?? new AnalyticsFilterData();

        return $this->velocityKpi->getAll($filter);
    }

    protected function getDrillDownUrls(): array
    {
        $urls = [];
        try {
            if (class_exists(ContactResource::class)) {
                $urls['contacts'] = ContactResource::getUrl('index');
                $urls['contacts_qualified'] = ContactResource::getUrl('index', [
                    'tableFilters' => [
                        'qualification' => ['value' => 'qualified'],
                    ],
                ]);
            }
        } catch (Throwable) {
            $urls['contacts'] = '/admin/contacts';
            $urls['contacts_qualified'] = '/admin/contacts';
        }

        try {
            if (class_exists(OpportunityResource::class)) {
                $urls['opportunities'] = OpportunityResource::getUrl('index');
            }
        } catch (Throwable) {
            $urls['opportunities'] = '/admin/opportunities';
        }

        try {
            if (class_exists(ReservationResource::class)) {
                $urls['reservations'] = ReservationResource::getUrl('index');
            }
        } catch (Throwable) {
            $urls['reservations'] = '/admin/reservations';
        }

        try {
            if (class_exists(ContractResource::class)) {
                $urls['contracts'] = ContractResource::getUrl('index');
            }
        } catch (Throwable) {
            $urls['contracts'] = '/admin/contracts';
        }

        return $urls;
    }
}
