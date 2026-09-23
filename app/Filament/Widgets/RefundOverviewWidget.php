<?php

namespace App\Filament\Widgets;

use App\DTOs\Analytics\FinanceOverviewData;
use App\Services\Analytics\FinanceAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RefundOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 16;

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $data = app(FinanceAnalyticsService::class)->refunds();

        return [
            Stat::make('Demandés', FinanceOverviewData::formatFcfa($data->requestedAmount))
                ->description("{$data->requestedCount} dossier(s) · requested_at")
                ->descriptionIcon('heroicon-m-inbox')
                ->color($data->requestedCount > 0 ? 'warning' : 'gray')
                ->url($data->drillDownUrls['refunds_pending'] ?? null),

            Stat::make('Approuvés', FinanceOverviewData::formatFcfa($data->approvedAmount))
                ->description("{$data->approvedCount} · pas encore une sortie de trésorerie")
                ->descriptionIcon('heroicon-m-check')
                ->color('info')
                ->url($data->drillDownUrls['refunds_approved'] ?? null),

            Stat::make('En traitement', FinanceOverviewData::formatFcfa($data->processingAmount))
                ->description((string) $data->processingCount)
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($data->processingCount > 0 ? 'warning' : 'gray')
                ->url($data->drillDownUrls['refunds_processing'] ?? null),

            Stat::make('Exécutés', FinanceOverviewData::formatFcfa($data->executedAmount))
                ->description("{$data->executedCount} · processed_at · sortie réelle")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger')
                ->url($data->drillDownUrls['refunds_completed'] ?? null),

            Stat::make('Rejetés', FinanceOverviewData::formatFcfa($data->rejectedAmount))
                ->description((string) $data->rejectedCount)
                ->descriptionIcon('heroicon-m-x-mark')
                ->color('gray')
                ->url($data->drillDownUrls['refunds_rejected'] ?? null),

            Stat::make('Encaissement net', FinanceOverviewData::formatFcfa($data->netCollected))
                ->description('Brut validé − remboursements exécutés uniquement')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('success'),
        ];
    }
}
