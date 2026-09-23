<?php

namespace App\Filament\Widgets;

use App\Models\BuyerDocument;
use App\Models\Contract;
use App\Models\Reservation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContractOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        // 1. Réservations actives sans contrat
        $reservationsWithoutContract = Reservation::whereIn('status', ['option', 'confirmed'])
            ->doesntHave('contract')
            ->count();

        // 2. Contrats par statut
        $pendingReviewContracts = Contract::where('status', 'pending_review')->count();
        $sentContracts = Contract::where('status', 'sent')->count();
        $signedContracts = Contract::where('status', 'signed')->count();

        // 3. Pièces KYC
        $pendingKycDocs = BuyerDocument::where('status', 'pending')->count();
        $verifiedKycDocs = BuyerDocument::where('status', 'verified')->count();

        return [
            Stat::make('Réservations à Contractualiser', $reservationsWithoutContract)
                ->description('Contrat VEFA non encore généré')
                ->descriptionIcon('heroicon-m-document-plus')
                ->color($reservationsWithoutContract > 0 ? 'warning' : 'success'),

            Stat::make('Contrats en Revue Juridique', $pendingReviewContracts)
                ->description('En attente de validation manager')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($pendingReviewContracts > 0 ? 'info' : 'gray'),

            Stat::make('En Attente de Signature', $sentContracts)
                ->description("{$signedContracts} contrats signés au total")
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color($sentContracts > 0 ? 'primary' : 'success'),

            Stat::make('Dossiers KYC : Pièces à Vérifier', $pendingKycDocs)
                ->description("{$verifiedKycDocs} pièces validées")
                ->descriptionIcon('heroicon-m-identification')
                ->color($pendingKycDocs > 0 ? 'danger' : 'success'),
        ];
    }
}
