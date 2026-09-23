<?php

namespace App\DTOs\Analytics;

/**
 * V4.3 — Engagement commercial (stock du portefeuille).
 *
 * Dates de référence :
 *   CA engagé / CA confirmé → reserved_at
 *   CA encaissé             → payment_date des payments status=validated
 *   Solde à percevoir       → reste dû actuel (pas une cohorte de période)
 *
 * Taux d'encaissement du portefeuille
 *   = CA encaissé (tous temps, payments validés)
 *   / CA confirmé (tous temps, hors filtre de période)
 *
 * Ce n'est PAS : encaissement du mois / CA contractuel du mois.
 * Voir FinancePeriodData pour les flux de période.
 */
readonly class FinanceOverviewData
{
    public function __construct(
        /** Σ total_amount des réservations option + confirmed + completed */
        public float $engagedRevenue,

        /** Σ total_amount des réservations confirmed + completed */
        public float $confirmedRevenue,

        /** Σ payments.status=validated (portefeuille, hors filtre de période) */
        public float $collectedRevenue,

        /** Solde à percevoir = max(0, CA confirmé − CA encaissé portefeuille) */
        public float $remainingBalance,

        /** Taux d'encaissement du portefeuille (%) */
        public float $collectionRate,

        /** Nombre de dossiers (réservations) avec au moins une échéance échue */
        public int $overdueReservationCount,

        /** URLs de drill-down Filament */
        public array $drillDownUrls = [],
    ) {}

    public static function formatFcfa(float $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }
}
