<?php

namespace App\Filament\Widgets;

use App\DTOs\Analytics\FinanceOverviewData;
use App\Services\Analytics\FinanceAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 13;

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $service = app(FinanceAnalyticsService::class);
        $data = $service->overview();
        $period = $service->period();

        $rateColor = match (true) {
            $data->collectionRate >= 80 => 'success',
            $data->collectionRate >= 50 => 'warning',
            default => 'danger',
        };

        return [
            Stat::make('CA contractuel engagé', FinanceOverviewData::formatFcfa($data->engagedRevenue))
                ->description('Options + confirmées + soldées · reserved_at')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('gray')
                ->url($data->drillDownUrls['reservations'] ?? null),

            Stat::make('CA confirmé', FinanceOverviewData::formatFcfa($data->confirmedRevenue))
                ->description('Confirmées + soldées · reserved_at')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('primary')
                ->url($data->drillDownUrls['reservations_confirmed'] ?? null),

            Stat::make('CA encaissé', FinanceOverviewData::formatFcfa($data->collectedRevenue))
                ->description(
                    'Portefeuille · payments validés · période : '
                    . FinanceOverviewData::formatFcfa($period->collectedInPeriod)
                )
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($rateColor)
                ->url($data->drillDownUrls['schedules'] ?? null),

            Stat::make('Solde à percevoir', FinanceOverviewData::formatFcfa($data->remainingBalance))
                ->description("Taux portefeuille : {$data->collectionRate}%")
                ->descriptionIcon('heroicon-m-clock')
                ->color($data->remainingBalance > 0 ? 'warning' : 'success')
                ->url($data->drillDownUrls['overdue'] ?? null),

            Stat::make('Taux d\'encaissement', "{$data->collectionRate} %")
                ->description('Encaissé global / CA confirmé (pas un taux mensuel)')
                ->descriptionIcon('heroicon-m-calculator')
                ->color($rateColor),

            Stat::make('Dossiers en retard', (string) $data->overdueReservationCount)
                ->description('Au moins une échéance échue non soldée')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($data->overdueReservationCount > 0 ? 'danger' : 'success')
                ->url($data->drillDownUrls['overdue'] ?? null),
        ];
    }
}
