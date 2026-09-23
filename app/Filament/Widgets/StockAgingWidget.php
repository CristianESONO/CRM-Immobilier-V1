<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\StockAnalyticsService;
use Filament\Widgets\ChartWidget;

class StockAgingWidget extends ChartWidget
{
    protected static ?string $heading = 'Ancienneté du Stock Disponible (Jours)';

    protected static ?int $sort = 8;

    protected function getData(): array
    {
        /** @var StockAnalyticsService $service */
        $service = app(StockAnalyticsService::class);
        $aging = $service->aging();

        return $aging->toChartData();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
