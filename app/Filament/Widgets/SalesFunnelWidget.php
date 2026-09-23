<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\SalesAnalyticsService;
use Filament\Widgets\ChartWidget;

class SalesFunnelWidget extends ChartWidget
{
    protected static ?string $heading = 'Entonnoir Commercial de Vente (Funnel)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        /** @var SalesAnalyticsService $service */
        $service = app(SalesAnalyticsService::class);
        $funnel = $service->getFunnel();

        return $funnel->toChartData();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y', // Barres horizontales type entonnoir
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => true,
                    ],
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
                'y' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }
}
