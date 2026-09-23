<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\SalesAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        /** @var SalesAnalyticsService $service */
        $service = app(SalesAnalyticsService::class);
        $data = $service->getOverview();

        $stats = [
            Stat::make('Prospects Entrants', $data->totalProspects)
                ->description("{$data->qualifiedProspects} qualifiés ({$data->qualificationRate}%)")
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info')
                ->url($data->drillDownUrls['contacts'] ?? null),

            Stat::make('Opportunités en Cours', $data->totalOpportunities)
                ->description("Transformation résa : {$data->opportunityToReservationRate}%")
                ->descriptionIcon('heroicon-m-funnel')
                ->color('primary')
                ->url($data->drillDownUrls['opportunities'] ?? null),

            Stat::make('Réservations Actives', $data->totalReservations)
                ->description("Panier moy. : " . number_format($data->averageBasket, 0, ',', ' ') . " FCFA")
                ->descriptionIcon('heroicon-m-document-check')
                ->color('success')
                ->url($data->drillDownUrls['reservations'] ?? null),

            Stat::make('Contrats Signés', $data->totalSignedContracts)
                ->description("Taux concrétisation : {$data->reservationToSignatureRate}%")
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url($data->drillDownUrls['contracts'] ?? null),

            Stat::make('CA Réservé', number_format($data->reservedRevenue, 0, ',', ' ') . ' FCFA')
                ->description("Encaissé : " . number_format($data->collectedRevenue, 0, ',', ' ') . " FCFA ({$data->collectionRate}%)")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary')
                ->url($data->drillDownUrls['reservations'] ?? null),

            Stat::make('Reste à Encaisser', number_format($data->remainingRevenue, 0, ',', ' ') . ' FCFA')
                ->description('Solde actif sur réservations')
                ->descriptionIcon('heroicon-m-clock')
                ->color($data->remainingRevenue > 0 ? 'warning' : 'success')
                ->url($data->drillDownUrls['reservations'] ?? null),
        ];

        return $stats;
    }
}
