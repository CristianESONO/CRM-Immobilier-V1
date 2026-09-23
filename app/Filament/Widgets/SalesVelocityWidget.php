<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\SalesAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesVelocityWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        /** @var SalesAnalyticsService $service */
        $service = app(SalesAnalyticsService::class);
        $velocities = $service->getVelocityMetrics();

        $slaHours = $velocities['average_first_response_hours'] ?? 0;
        $leadToQualDays = $velocities['average_lead_to_qualified_days'] ?? 0;
        $qualToResaDays = $velocities['average_qualified_to_reservation_days'] ?? 0;
        $resaToSignedDays = $velocities['average_reservation_to_signed_days'] ?? 0;

        return [
            Stat::make('SLA 1ère Réponse', "{$slaHours} h")
                ->description('Cible < 2h ouvrées')
                ->descriptionIcon('heroicon-m-bolt')
                ->color($slaHours <= 2.0 ? 'success' : 'warning'),

            Stat::make('Cycle Contact ➔ Qualif', "{$leadToQualDays} j")
                ->description('Délai moyen d\'instruction')
                ->descriptionIcon('heroicon-m-clock')
                ->color($leadToQualDays <= 3.0 ? 'success' : 'info'),

            Stat::make('Cycle Qualif ➔ Réservation', "{$qualToResaDays} j")
                ->description('Temps de négociation & choix lot')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($qualToResaDays <= 14.0 ? 'success' : 'warning'),

            Stat::make('Cycle Résa ➔ Signature', "{$resaToSignedDays} j")
                ->description('Délai d\'instruction juridique VEFA')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($resaToSignedDays <= 10.0 ? 'success' : 'danger'),
        ];
    }
}
