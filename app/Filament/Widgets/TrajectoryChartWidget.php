<?php

namespace App\Filament\Widgets;

use App\Models\Contact;
use Filament\Widgets\ChartWidget;

class TrajectoryChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Écart à la Trajectoire : Prospects Qualifiés (Réel vs Cible GRET INVEST)';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $expectedTargets = [0, 15, 40, 70, 115, 150];

        $qualifiedCount = Contact::whereNotNull('qualified_at')->count();
        $realData = [$qualifiedCount, $qualifiedCount, $qualifiedCount, $qualifiedCount, $qualifiedCount, $qualifiedCount];

        return [
            'datasets' => [
                [
                    'label' => 'Attendu (Trajectoire Pilote)',
                    'data' => $expectedTargets,
                    'borderColor' => '#94a3b8',
                    'borderDash' => [5, 5],
                    'fill' => false,
                ],
                [
                    'label' => 'Réel (Prospects Qualifiés)',
                    'data' => $realData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => ['Mois 1', 'Mois 2 (15)', 'Mois 3 (40)', 'Mois 4 (70)', 'Mois 5 (115)', 'Mois 6 (150)'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
