<?php

namespace App\DTOs\Analytics;

/**
 * V4.3 — Créances / échéances ouvertes.
 *
 * Date de référence : due_date.
 * Retard (jours)    : aujourd'hui − due_date (0 si due_date ≥ aujourd'hui).
 * Montants          : reste dû = expected_amount − paid_amount (jamais l'échéance brute).
 * Les échéances soldées (reste dû ≤ 0) sont exclues.
 */
readonly class OverdueAgingData
{
    public function __construct(
        public float $currentAmount,
        public float $bucket1to7,
        public float $bucket8to30,
        public float $bucket31to60,
        public float $bucket61to90,
        public float $bucketOver90,
        public int $openScheduleCount,
        public int $overdueScheduleCount,
        public float $overdueAmount,
        public int $overdueReservationCount,
        public float $averageOverdueDays,
        public float $medianOverdueDays,
        public array $drillDownUrls = [],
    ) {}

    public function totalOpenAmount(): float
    {
        return $this->currentAmount
            + $this->bucket1to7
            + $this->bucket8to30
            + $this->bucket31to60
            + $this->bucket61to90
            + $this->bucketOver90;
    }

    public function toChartData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Reste dû (M FCFA)',
                    'data' => [
                        round($this->currentAmount / 1_000_000, 2),
                        round($this->bucket1to7 / 1_000_000, 2),
                        round($this->bucket8to30 / 1_000_000, 2),
                        round($this->bucket31to60 / 1_000_000, 2),
                        round($this->bucket61to90 / 1_000_000, 2),
                        round($this->bucketOver90 / 1_000_000, 2),
                    ],
                    'backgroundColor' => [
                        'rgba(16, 185, 129, 0.75)',
                        'rgba(6, 182, 212, 0.75)',
                        'rgba(245, 158, 11, 0.75)',
                        'rgba(249, 115, 22, 0.75)',
                        'rgba(239, 68, 68, 0.75)',
                        'rgba(127, 29, 29, 0.8)',
                    ],
                    'borderRadius' => 6,
                ],
            ],
            'labels' => ['À jour', '1–7 j', '8–30 j', '31–60 j', '61–90 j', '> 90 j'],
        ];
    }
}
