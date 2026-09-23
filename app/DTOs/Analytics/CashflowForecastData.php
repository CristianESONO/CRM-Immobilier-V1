<?php

namespace App\DTOs\Analytics;

/**
 * V4.3 — Cashflow prévisionnel sur le reste dû.
 *
 * Date de référence : due_date.
 * Fenêtres cumulatives :
 *   today    = Σ reste dû où due_date ≤ aujourd'hui
 *   next30   = Σ reste dû où due_date ≤ aujourd'hui + 30 j
 *   next60   = Σ reste dû où due_date ≤ aujourd'hui + 60 j
 *   next90   = Σ reste dû où due_date ≤ aujourd'hui + 90 j
 *
 * Les montants déjà encaissés (paid_amount) sont exclus.
 * Une échéance soldée contribue 0.
 */
readonly class CashflowForecastData
{
    public function __construct(
        public float $today,
        public float $next30Days,
        public float $next60Days,
        public float $next90Days,
        public array $drillDownUrls = [],
    ) {}

    public function toChartData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Reste dû cumulé (M FCFA)',
                    'data' => [
                        round($this->today / 1_000_000, 2),
                        round($this->next30Days / 1_000_000, 2),
                        round($this->next60Days / 1_000_000, 2),
                        round($this->next90Days / 1_000_000, 2),
                    ],
                    'backgroundColor' => [
                        'rgba(239, 68, 68, 0.75)',
                        'rgba(16, 185, 129, 0.75)',
                        'rgba(245, 158, 11, 0.75)',
                        'rgba(99, 102, 241, 0.75)',
                    ],
                    'borderRadius' => 6,
                ],
            ],
            'labels' => ['Aujourd\'hui', 'J+30', 'J+60', 'J+90'],
        ];
    }
}
