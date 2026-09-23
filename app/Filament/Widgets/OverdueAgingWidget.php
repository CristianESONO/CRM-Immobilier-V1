<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\FinanceAnalyticsService;
use Filament\Widgets\ChartWidget;

class OverdueAgingWidget extends ChartWidget
{
    protected static ?string $heading = 'Créances par ancienneté (reste dû, M FCFA)';

    protected static ?int $sort = 14;

    protected static ?string $pollingInterval = null;

    protected function getData(): array
    {
        $aging = app(FinanceAnalyticsService::class)->overdueAging();

        return $aging->toChartData();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'Millions FCFA'],
                ],
            ],
        ];
    }
}
