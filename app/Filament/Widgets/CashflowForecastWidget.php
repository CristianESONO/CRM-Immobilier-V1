<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\FinanceAnalyticsService;
use Filament\Widgets\ChartWidget;

class CashflowForecastWidget extends ChartWidget
{
    protected static ?string $heading = 'Cashflow prévisionnel — reste dû cumulé (M FCFA)';

    protected static ?int $sort = 15;

    protected static ?string $pollingInterval = null;

    protected function getData(): array
    {
        $forecast = app(FinanceAnalyticsService::class)->cashflowForecast();

        return $forecast->toChartData();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'Millions FCFA'],
                ],
            ],
        ];
    }
}
