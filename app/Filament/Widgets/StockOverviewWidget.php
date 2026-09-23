<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\StockAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        /** @var StockAnalyticsService $service */
        $service = app(StockAnalyticsService::class);
        $data = $service->overview();

        return [
            Stat::make('Stock Total', $data->totalUnits)
                ->description("{$data->availableUnits} dispo · {$data->reservedUnits} résa · {$data->soldUnits} vendus")
                ->descriptionIcon('heroicon-m-home-modern')
                ->color('info')
                ->url($data->drillDownUrls['all'] ?? null),

            Stat::make('Taux d\'Écoulement', "{$data->sellThroughRate} %")
                ->description("{$data->soldUnits} vendus sur {$data->totalUnits} lots")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($data->sellThroughRate >= 50 ? 'success' : 'warning'),

            Stat::make('Lots Disponibles', $data->availableUnits)
                ->description("{$data->availabilityRate}% du stock commercial")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url($data->drillDownUrls['available'] ?? null),

            Stat::make('Valeur Stock Dispo', number_format($data->availableStockValue, 0, ',', ' ') . ' FCFA')
                ->description("Total stock : " . number_format($data->totalStockValue, 0, ',', ' ') . " FCFA")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary')
                ->url($data->drillDownUrls['available'] ?? null),

            Stat::make('Prix / m² Pondéré', number_format($data->weightedPricePerSqm, 0, ',', ' ') . ' FCFA')
                ->description("Dispo : " . number_format($data->availableWeightedPricePerSqm, 0, ',', ' ') . " FCFA/m²")
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make('Surface Disponible', number_format($data->availableSurface, 1, ',', ' ') . ' m²')
                ->description("Moyenne : {$data->averageSurface} m² / lot")
                ->descriptionIcon('heroicon-m-square-3-stack-3d')
                ->color('info'),

            Stat::make('Âge Moyen du Stock', "{$data->averageAgingDays} jours")
                ->description('Depuis commercialisation')
                ->descriptionIcon('heroicon-m-clock')
                ->color($data->averageAgingDays <= 90 ? 'success' : ($data->averageAgingDays <= 180 ? 'warning' : 'danger')),
        ];
    }
}
