<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\StockAnalyticsService;
use Filament\Widgets\ChartWidget;

class StockByTypeWidget extends ChartWidget
{
    protected static ?string $heading = 'Répartition du Stock par Typologie';

    protected static ?int $sort = 7;

    protected function getData(): array
    {
        /** @var StockAnalyticsService $service */
        $service = app(StockAnalyticsService::class);
        $types = $service->byType();

        $labels = [];
        $available = [];
        $reserved = [];
        $sold = [];

        foreach ($types as $type) {
            $labels[] = $type->typology;
            $available[] = $type->availableUnits;
            $reserved[] = $type->reservedUnits;
            $sold[] = $type->soldUnits;
        }

        if (empty($labels)) {
            $labels = ['Aucune typologie'];
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
