<?php

namespace App\DTOs\Analytics;

class FunnelData
{
    /**
     * @param array<FunnelStepData> $steps
     */
    public function __construct(
        public readonly array $steps,
        public readonly int $totalProspects,
        public readonly int $totalSigned,
        public readonly float $overallConversionRate,
    ) {}

    public function toChartData(): array
    {
        $labels = [];
        $data = [];
        $colors = [];

        foreach ($this->steps as $step) {
            $labels[] = "{$step->label} ({$step->count})";
            $data[] = $step->count;
            $colors[] = $step->color;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Dossiers',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
