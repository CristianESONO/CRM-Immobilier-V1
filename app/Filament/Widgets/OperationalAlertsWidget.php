<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OperationalAlertResource;
use App\Models\Tenant;
use App\Services\Alerts\OperationalAlertService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

class OperationalAlertsWidget extends BaseWidget
{
    protected static ?int $sort = 0; // Prioritaire en haut de dashboard

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $user = auth()->user();
        $tenant = $user?->tenant ?? Tenant::first();

        if (!$tenant) {
            return [
                Stat::make('Alertes Opérationnelles', '0')
                    ->description('Aucun tenant configuré')
                    ->color('gray'),
            ];
        }

        $service = app(OperationalAlertService::class);
        $summary = $service->getSummaryForTenant($tenant);

        $indexUrl = null;
        try {
            if (class_exists(OperationalAlertResource::class)) {
                $indexUrl = OperationalAlertResource::getUrl('index');
            }
        } catch (Throwable) {
            // fallback
        }

        return [
            Stat::make('CRITICAL (Critiques)', (string) $summary['critical'])
                ->description('Intervention urgente requise')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($summary['critical'] > 0 ? 'danger' : 'success')
                ->url($indexUrl),

            Stat::make('WARNING (Avertissements)', (string) $summary['warning'])
                ->description('À traiter à court terme')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($summary['warning'] > 0 ? 'warning' : 'success')
                ->url($indexUrl),

            Stat::make('INFO (Informations)', (string) $summary['info'])
                ->description('Échéances & suivis préventifs')
                ->descriptionIcon('heroicon-m-information-circle')
                ->color('info')
                ->url($indexUrl),

            Stat::make('Total Alertes Ouvertes', (string) $summary['total_open'])
                ->description('Problèmes actifs nécessitant action')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color($summary['total_open'] > 0 ? 'danger' : 'success')
                ->url($indexUrl),
        ];
    }
}
