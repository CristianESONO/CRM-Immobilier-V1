<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Reservation;
use Filament\Widgets\ChartWidget;

class CashflowWidget extends ChartWidget
{
    protected static ?string $heading = 'Trésorerie : CA Contractuel vs Encaissé vs Prévu (6 mois)';

    protected static ?int $sort = 5;

    protected function getData(): array
    {
        $months = [];
        $caContractuel = [];
        $caEncaisse = [];
        $caPrevisionnel = [];

        // Build rolling 6-month window (current month + 5 upcoming)
        for ($i = 0; $i < 6; $i++) {
            $date = now()->startOfMonth()->addMonths($i);
            $months[] = $date->translatedFormat('M Y');

            if ($i === 0) {
                // Current month: contractual value of all active reservations (cumulative)
                $contractual = Reservation::whereIn('status', ['option', 'confirmed', 'completed'])
                    ->sum('total_amount');
                $caContractuel[] = round($contractual / 1000000, 2); // en millions FCFA

                // Already collected payments validated up to today
                $collected = Payment::where('status', 'validated')
                    ->whereDate('payment_date', '<=', now())
                    ->sum('amount');
                $caEncaisse[] = round($collected / 1000000, 2);
            } else {
                // Future months: not applicable for contractual/collected
                $caContractuel[] = null;
                $caEncaisse[] = null;
            }

            // Forecasted: scheduled due dates falling in this month (pending/partial only)
            $monthStart = $date->copy()->startOfMonth()->toDateString();
            $monthEnd = $date->copy()->endOfMonth()->toDateString();

            $forecasted = PaymentSchedule::whereIn('status', ['pending', 'partial'])
                ->whereBetween('due_date', [$monthStart, $monthEnd])
                ->sum('expected_amount');

            $caPrevisionnel[] = round($forecasted / 1000000, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'CA Contractuel total (M FCFA)',
                    'data' => $caContractuel,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99,102,241,0.12)',
                    'type' => 'bar',
                ],
                [
                    'label' => 'Encaissé cumulé (M FCFA)',
                    'data' => $caEncaisse,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16,185,129,0.15)',
                    'type' => 'bar',
                ],
                [
                    'label' => 'Prévisions d\'encaissement (M FCFA)',
                    'data' => $caPrevisionnel,
                    'borderColor' => '#f59e0b',
                    'borderDash' => [5, 5],
                    'fill' => false,
                    'type' => 'line',
                ],
            ],
            'labels' => $months,
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
                'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Millions FCFA']],
            ],
        ];
    }
}
