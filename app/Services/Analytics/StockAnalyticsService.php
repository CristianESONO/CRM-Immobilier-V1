<?php

namespace App\Services\Analytics;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\DTOs\Analytics\StockAgingData;
use App\DTOs\Analytics\StockOverviewData;
use App\DTOs\Analytics\StockProgramBreakdownData;
use App\DTOs\Analytics\StockTypeBreakdownData;
use App\Filament\Resources\UnitResource;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Analytics\Kpi\Stock\StockAgingKpi;
use App\Services\Analytics\Kpi\Stock\StockPricingKpi;
use App\Services\Analytics\Kpi\Stock\StockVolumeKpi;
use Illuminate\Support\Collection;
use Throwable;

class StockAnalyticsService
{
    public function __construct(
        protected StockVolumeKpi $volumeKpi = new StockVolumeKpi(),
        protected StockPricingKpi $pricingKpi = new StockPricingKpi(),
        protected StockAgingKpi $agingKpi = new StockAgingKpi(),
    ) {}

    public function overview(?AnalyticsFilterData $filter = null): StockOverviewData
    {
        $filter = $filter ?? new AnalyticsFilterData();

        // 1. Volumes
        $total = $this->volumeKpi->getTotalUnits($filter);
        $available = $this->volumeKpi->getAvailableUnits($filter);
        $reserved = $this->volumeKpi->getReservedUnits($filter);
        $sold = $this->volumeKpi->getSoldUnits($filter);

        // 2. Ratios
        $sellThroughRate = $this->volumeKpi->getSellThroughRate($sold, $total);
        $reservationRate = $this->volumeKpi->getReservationRate($reserved, $total);
        $availabilityRate = $this->volumeKpi->getAvailabilityRate($available, $total);

        // 3. Valeurs
        $totalVal = $this->pricingKpi->getStockValue($filter);
        $availVal = $this->pricingKpi->getStockValue($filter, 'available');
        $resVal = $this->pricingKpi->getStockValue($filter, 'reserved');
        $soldVal = $this->pricingKpi->getStockValue($filter, 'sold');

        // 4. Surfaces
        $totalSurface = $this->pricingKpi->getTotalSurface($filter);
        $availableSurface = $this->pricingKpi->getTotalSurface($filter, 'available');
        $averageSurface = $this->pricingKpi->getAverageSurface($filter);

        // 5. Prix au m² pondéré
        $weightedAll = $this->pricingKpi->getWeightedPricePerSqm($filter);
        $weightedAvail = $this->pricingKpi->getWeightedPricePerSqm($filter, 'available');
        $weightedRes = $this->pricingKpi->getWeightedPricePerSqm($filter, 'reserved');
        $weightedSold = $this->pricingKpi->getWeightedPricePerSqm($filter, 'sold');

        // 6. Ancienneté
        $agingData = $this->agingKpi->getAging($filter);

        // 7. Drill-down URLs
        $urls = $this->getDrillDownUrls();

        return new StockOverviewData(
            totalUnits: $total,
            availableUnits: $available,
            reservedUnits: $reserved,
            soldUnits: $sold,
            sellThroughRate: $sellThroughRate,
            reservationRate: $reservationRate,
            availabilityRate: $availabilityRate,
            totalStockValue: $totalVal,
            availableStockValue: $availVal,
            reservedStockValue: $resVal,
            soldStockValue: $soldVal,
            totalSurface: $totalSurface,
            availableSurface: $availableSurface,
            averageSurface: $averageSurface,
            weightedPricePerSqm: $weightedAll,
            availableWeightedPricePerSqm: $weightedAvail,
            reservedWeightedPricePerSqm: $weightedRes,
            soldWeightedPricePerSqm: $weightedSold,
            averageAgingDays: $agingData->averageDays,
            drillDownUrls: $urls,
        );
    }

    /**
     * @return array<StockProgramBreakdownData>
     */
    public function byProgram(?AnalyticsFilterData $filter = null): array
    {
        $filter = $filter ?? new AnalyticsFilterData();

        $propertiesQuery = Property::query();
        if ($filter->propertyId) {
            $propertiesQuery->where('id', $filter->propertyId);
        }

        $properties = $propertiesQuery->with(['units'])->get();
        $breakdown = [];

        foreach ($properties as $property) {
            $units = $property->units;
            if ($filter->unitType) {
                $units = $units->where('typology', $filter->unitType);
            }

            $total = $units->count();
            $available = $units->where('status', 'available')->count();
            $reserved = $units->where('status', 'reserved')->count();
            $sold = $units->where('status', 'sold')->count();

            $sellThrough = $this->volumeKpi->getSellThroughRate($sold, $total);
            $availValue = (float) $units->where('status', 'available')->sum('price');
            $totalValue = (float) $units->sum('price');

            $unitsWithArea = $units->where('area', '>', 0);
            $sumPrice = (float) $unitsWithArea->sum('price');
            $sumArea = (float) $unitsWithArea->sum('area');
            $weightedPsqm = $sumArea > 0 ? round($sumPrice / $sumArea, 2) : 0.0;

            $url = null;
            try {
                if (class_exists(UnitResource::class)) {
                    $url = UnitResource::getUrl('index', [
                        'tableFilters' => [
                            'property_id' => ['value' => $property->id],
                        ],
                    ]);
                }
            } catch (Throwable) {
                $url = "/admin/units?property_id={$property->id}";
            }

            $breakdown[] = new StockProgramBreakdownData(
                propertyId: $property->id,
                propertyName: $property->name,
                totalUnits: $total,
                availableUnits: $available,
                reservedUnits: $reserved,
                soldUnits: $sold,
                sellThroughRate: $sellThrough,
                availableValue: $availValue,
                totalValue: $totalValue,
                weightedPricePerSqm: $weightedPsqm,
                drillDownUrl: $url,
            );
        }

        return $breakdown;
    }

    /**
     * @return array<StockTypeBreakdownData>
     */
    public function byType(?AnalyticsFilterData $filter = null): array
    {
        $filter = $filter ?? new AnalyticsFilterData();

        $query = Unit::query();
        if ($filter->propertyId) {
            $query->where('property_id', $filter->propertyId);
        }

        $allUnits = $query->get();
        $grouped = $allUnits->groupBy(fn ($u) => $u->typology ?: 'Autre');
        $breakdown = [];

        foreach ($grouped as $typology => $units) {
            $total = $units->count();
            $available = $units->where('status', 'available')->count();
            $reserved = $units->where('status', 'reserved')->count();
            $sold = $units->where('status', 'sold')->count();

            $availValue = (float) $units->where('status', 'available')->sum('price');
            $availSurface = (float) $units->where('status', 'available')->sum('area');

            $unitsWithArea = $units->where('area', '>', 0);
            $sumPrice = (float) $unitsWithArea->sum('price');
            $sumArea = (float) $unitsWithArea->sum('area');
            $weightedPsqm = $sumArea > 0 ? round($sumPrice / $sumArea, 2) : 0.0;

            $url = null;
            try {
                if (class_exists(UnitResource::class)) {
                    $url = UnitResource::getUrl('index', [
                        'tableFilters' => [
                            'typology' => ['value' => $typology],
                        ],
                    ]);
                }
            } catch (Throwable) {
                $url = "/admin/units?typology={$typology}";
            }

            $breakdown[] = new StockTypeBreakdownData(
                typology: (string) $typology,
                totalUnits: $total,
                availableUnits: $available,
                reservedUnits: $reserved,
                soldUnits: $sold,
                availableValue: $availValue,
                availableSurface: $availSurface,
                weightedPricePerSqm: $weightedPsqm,
                drillDownUrl: $url,
            );
        }

        return $breakdown;
    }

    public function aging(?AnalyticsFilterData $filter = null): StockAgingData
    {
        $filter = $filter ?? new AnalyticsFilterData();

        return $this->agingKpi->getAging($filter);
    }

    public function availableUnits(?AnalyticsFilterData $filter = null): Collection
    {
        $filter = $filter ?? new AnalyticsFilterData();
        $query = Unit::query()->where('status', 'available')->with('property');

        if ($filter->propertyId) {
            $query->where('property_id', $filter->propertyId);
        }
        if ($filter->unitType) {
            $query->where('typology', $filter->unitType);
        }

        return $query->get();
    }

    protected function getDrillDownUrls(): array
    {
        $urls = [];
        try {
            if (class_exists(UnitResource::class)) {
                $urls['all'] = UnitResource::getUrl('index');
                $urls['available'] = UnitResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['value' => 'available'],
                    ],
                ]);
                $urls['reserved'] = UnitResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['value' => 'reserved'],
                    ],
                ]);
                $urls['sold'] = UnitResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['value' => 'sold'],
                    ],
                ]);
            }
        } catch (Throwable) {
            $urls['all'] = '/admin/units';
            $urls['available'] = '/admin/units?status=available';
            $urls['reserved'] = '/admin/units?status=reserved';
            $urls['sold'] = '/admin/units?status=sold';
        }

        return $urls;
    }
}
