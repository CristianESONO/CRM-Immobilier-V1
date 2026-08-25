<?php

namespace App\Filament\Widgets;

use App\Models\Contact;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalContacts = Contact::count();
        $qualifiedContacts = Contact::whereNotNull('qualified_at')->count();
        $wonContacts = Contact::where('status', 'gagne')->count();

        // SLA moyen calculé (minutes)
        $avgResponseMinutes = Contact::whereNotNull('first_response_minutes')->avg('first_response_minutes') ?? 0;
        $formattedSla = round($avgResponseMinutes / 60, 1) . ' h';

        return [
            Stat::make('Total Prospects', $totalContacts)
                ->description('Tous canaux confondus')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info'),

            Stat::make('Prospects Qualifiés', $qualifiedContacts)
                ->description('4 conditions validées')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Ventes Gagnées', $wonContacts)
                ->description('Contrats conclus')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Délai 1ère Réponse (Moyen)', $formattedSla)
                ->description('SLA cible < 2h ouvrées')
                ->descriptionIcon('heroicon-m-clock')
                ->color($avgResponseMinutes <= 120 ? 'success' : 'warning'),
        ];
    }
}
