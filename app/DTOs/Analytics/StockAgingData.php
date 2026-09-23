<?php

namespace App\DTOs\Analytics;

class StockAgingData
{
    public function __construct(
        public readonly int $bucket0to30,
        public readonly int $bucket31to90,
        public readonly int $bucket91to180,
        public readonly int $bucket181to365,
        public readonly int $bucketOver365,
        public readonly float $averageDays,
    ) {}

    public function toChartData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Lots disponibles en stock',
                    'data' => [
                        $this->bucket0to30,
                        $this->bucket31to90,
                        $this->bucket91to180,
                        $this->bucket181to365,
                        $this->bucketOver365,
                    ],
                    'backgroundColor' => [
                        '#10b981', // 0-30 j : Vert (récent)
                        '#06b6d4', // 31-90 j : Cyan
                        '#f59e0b', // 91-180 j : Ambre
                        '#f97316', // 181-365 j : Orange
                        '#ef4444', // >365 j : Rouge (stock dormant)
                    ],
                    'borderRadius' => 6,
                ],
            ],
            'labels' => [
                '0–30 jours',
                '31–90 jours',
                '91–180 jours',
                '181–365 jours',
                '> 365 jours',
            ],
        ];
    }
}
