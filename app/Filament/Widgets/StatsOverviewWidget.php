<?php

namespace App\Filament\Widgets;

use App\Models\Contact;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return false; // Replaced by SalesOverviewWidget in V4
    }

    protected function getStats(): array
    {
        // --- Prospects & SLA ---
        $totalContacts = Contact::count();
        $qualifiedContacts = Contact::whereNotNull('qualified_at')->count();
        $avgResponseMinutes = Contact::whereNotNull('first_response_minutes')->avg('first_response_minutes') ?? 0;
        $formattedSla = round($avgResponseMinutes / 60, 1) . ' h';

        // --- Stock des Lots ---
        $lotsAvailable = Unit::where('status', 'available')->count();
        $lotsReserved = Unit::where('status', 'reserved')->count();
        $lotsSold = Unit::where('status', 'sold')->count();

        // --- Chiffre d'Affaires Contractuel vs Encaissé ---
        $caContractuel = Reservation::whereIn('status', ['confirmed', 'option', 'completed'])->sum('total_amount');
        $caEncaisse = Payment::where('status', 'validated')->sum('amount');
        $caRestant = max(0, $caContractuel - $caEncaisse);

        // --- Échéances en retard ---
        $overdueSchedules = \App\Models\PaymentSchedule::where('status', 'overdue')
            ->orWhere(function ($query) {
                $query->where('status', 'pending')
                    ->where('due_date', '<', now()->toDateString());
            })->count();

        return [
            // Ligne 1 : Prospects & Conversion
            Stat::make('Prospects Total', $totalContacts)
                ->description('Tous canaux confondus')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info'),

            Stat::make('Qualifiés', $qualifiedContacts)
                ->description('4 conditions validées')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('SLA 1ère Réponse', $formattedSla)
                ->description('Cible < 2h ouvrées')
                ->descriptionIcon('heroicon-m-clock')
                ->color($avgResponseMinutes <= 120 ? 'success' : 'danger'),

            // Ligne 2 : Stock des Lots
            Stat::make('Lots Disponibles', $lotsAvailable)
                ->description("{$lotsReserved} réservés · {$lotsSold} vendus")
                ->descriptionIcon('heroicon-m-home-modern')
                ->color('info'),

            // Ligne 3 : Financier
            Stat::make('CA Contractuel', number_format($caContractuel, 0, ',', ' ') . ' FCFA')
                ->description('Réservations actives')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('primary'),

            Stat::make('Encaissé', number_format($caEncaisse, 0, ',', ' ') . ' FCFA')
                ->description('Solde restant : ' . number_format($caRestant, 0, ',', ' ') . ' FCFA')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($caRestant > 0 ? 'warning' : 'success'),

            Stat::make('Échéances en Retard', $overdueSchedules)
                ->description('Appels de fonds non réglés')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueSchedules > 0 ? 'danger' : 'success'),
        ];
    }
}
