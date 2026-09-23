<?php

namespace App\Services\Analytics\Kpi;

class ConversionKpi
{
    /**
     * Calcule un taux de conversion en pourcentage sécurisé contre la division par zéro.
     */
    public function safeRate(int|float $numerator, int|float $denominator, int $precision = 1): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(((float) $numerator / (float) $denominator) * 100, $precision);
    }

    public function getQualificationRate(int $qualified, int $prospects): float
    {
        return $this->safeRate($qualified, $prospects);
    }

    public function getOpportunityToReservationRate(int $reservations, int $opportunities): float
    {
        return $this->safeRate($reservations, $opportunities);
    }

    public function getReservationToSignatureRate(int $signatures, int $reservations): float
    {
        return $this->safeRate($signatures, $reservations);
    }

    public function getGlobalConversionRate(int $signatures, int $prospects): float
    {
        return $this->safeRate($signatures, $prospects);
    }

    /**
     * Calcule les taux de conversion d'étape en étape et globaux pour tout le funnel.
     */
    public function calculateFunnelConversions(array $volumes): array
    {
        $prospects = $volumes['prospects'] ?? 0;
        $qualifies = $volumes['qualifies'] ?? 0;
        $opportunites = $volumes['opportunites'] ?? 0;
        $visites = $volumes['visites'] ?? 0;
        $offres = $volumes['offres'] ?? 0;
        $reservations = $volumes['reservations'] ?? 0;
        $signatures = $volumes['signatures'] ?? 0;

        return [
            'prospect_to_qualifie' => $this->safeRate($qualifies, $prospects),
            'qualifie_to_opportunite' => $this->safeRate($opportunites, $qualifies),
            'opportunite_to_visite' => $this->safeRate($visites, $opportunites),
            'visite_to_offre' => $this->safeRate($offres, $visites),
            'offre_to_reservation' => $this->safeRate($reservations, $offres),
            'reservation_to_signature' => $this->safeRate($signatures, $reservations),
            'global_lead_to_sale' => $this->safeRate($signatures, $prospects),
        ];
    }
}
