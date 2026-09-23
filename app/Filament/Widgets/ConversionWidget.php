<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\SalesAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ConversionWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        /** @var SalesAnalyticsService $service */
        $service = app(SalesAnalyticsService::class);
        $conversions = $service->getConversionMetrics();

        return [
            Stat::make('Lead ➔ Qualifié', ($conversions['prospect_to_qualifie'] ?? 0) . ' %')
                ->description('Taux de qualification initial')
                ->descriptionIcon('heroicon-m-funnel')
                ->color(($conversions['prospect_to_qualifie'] ?? 0) >= 50 ? 'success' : 'warning'),

            Stat::make('Opportunité ➔ Visite', ($conversions['opportunite_to_visite'] ?? 0) . ' %')
                ->description('Taux d\'accès à la visite')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color(($conversions['opportunite_to_visite'] ?? 0) >= 50 ? 'success' : 'warning'),

            Stat::make('Visite ➔ Offre', ($conversions['visite_to_offre'] ?? 0) . ' %')
                ->description('Proposition commerciale émise')
                ->descriptionIcon('heroicon-m-document-text')
                ->color(($conversions['visite_to_offre'] ?? 0) >= 30 ? 'success' : 'warning'),

            Stat::make('Offre ➔ Réservation', ($conversions['offre_to_reservation'] ?? 0) . ' %')
                ->description('Aboutissement en réservation')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color(($conversions['offre_to_reservation'] ?? 0) >= 30 ? 'success' : 'warning'),

            Stat::make('Réservation ➔ Signature', ($conversions['reservation_to_signature'] ?? 0) . ' %')
                ->description('Concrétisation contractuelle')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color(($conversions['reservation_to_signature'] ?? 0) >= 70 ? 'success' : 'danger'),

            Stat::make('Conversion Globale', ($conversions['global_lead_to_sale'] ?? 0) . ' %')
                ->description('De prospect entrant à contrat signé')
                ->descriptionIcon('heroicon-m-trophy')
                ->color(($conversions['global_lead_to_sale'] ?? 0) >= 5 ? 'success' : 'primary'),
        ];
    }
}
