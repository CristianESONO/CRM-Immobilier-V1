<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\StockAnalyticsService;
use Filament\Widgets\ChartWidget;

class StockByProgramWidget extends ChartWidget
{
    protected static ?string $heading = 'Stock par Programme : Disponibles / Réservés / Vendus';

    protected static ?int $sort = 6;

    protected function getData(): array
    {
        /** @var StockAnalyticsService $service */
        $service = app(StockAnalyticsService::class);
        $programs = $service->byProgram();

        $labels = [];
        $available = [];
        $reserved = [];
        $sold = [];

        foreach ($programs as $program) {
            $labels[] = "{$program->propertyName} ({$program->sellThroughRate}%)";
            $available[] = $program->availableUnits;
            $reserved[] = $program->reservedUnits;
            $sold[] = $program->soldUnits;
        }

        if (empty($labels)) {
            $labels = ['Aucun programme'];
            $available = [0];
            $reserved = [0];
            $sold = [0];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Disponibles',
                    'data' => $available,
                    'backgroundColor' => '#10b981', // green
                ],
                [
                    'label' => 'Réservés',
                    'data' => $reserved,
                    'backgroundColor' => '#f59e0b', // amber
                ],
                [
                    'label' => 'Vendus',
                    'data' => $sold,
                    'backgroundColor' => '#6366f1', // indigo
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true],
                'tooltip' => ['mode' => 'index'],
            ],
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true],
            ],
        ];
    }
}
