<?php

namespace App\Filament\Widgets;

use App\Models\Contact;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PipelineChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Répartition des Prospects par Étape du Pipeline';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $statuses = ['nouveau', 'contacte', 'qualifie', 'rdv_planifie', 'visite_realisee', 'proposition', 'gagne', 'perdu'];
        
        $counts = [];
        foreach ($statuses as $status) {
            $counts[] = Contact::where('status', $status)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Nombre de prospects',
                    'data' => $counts,
                    'backgroundColor' => [
                        '#3b82f6', '#06b6d4', '#10b981', '#6366f1',
                        '#8b5cf6', '#f59e0b', '#22c55e', '#ef4444'
                    ],
                ],
            ],
            'labels' => ['Nouveau', 'Contacté', 'Qualifié', 'RDV Planifié', 'Visite Réalisée', 'Proposition', 'Gagné', 'Perdu'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
