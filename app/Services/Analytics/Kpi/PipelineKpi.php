<?php

namespace App\Services\Analytics\Kpi;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Reservation;

class PipelineKpi
{
    public function getProspectsCount(AnalyticsFilterData $filter): int
    {
        $query = Contact::query();
        $query = $filter->applyDateFilter($query, 'created_at');

        if ($filter->assignedTo) {
            $query->where('assigned_to', $filter->assignedTo);
        }

        return $query->count();
    }

    public function getQualifiedProspectsCount(AnalyticsFilterData $filter): int
    {
        $query = Contact::query()->whereNotNull('qualified_at');
        $query = $filter->applyDateFilter($query, 'created_at');

        if ($filter->assignedTo) {
            $query->where('assigned_to', $filter->assignedTo);
        }

        return $query->count();
    }

    public function getOpportunitiesCount(AnalyticsFilterData $filter): int
    {
        $query = Opportunity::query();
        $query = $filter->applyDateFilter($query, 'created_at');

        if ($filter->propertyId) {
            $query->where('property_id', $filter->propertyId);
        }
        if ($filter->assignedTo) {
            $query->where('assigned_to', $filter->assignedTo);
        }

        return $query->count();
    }

    public function getVisitsCount(AnalyticsFilterData $filter): int
    {
        // Visites réalisées ou étapes ultérieures
        $query = Opportunity::query()->whereIn('stage', [
            'visite_realisee',
            'offre_en_cours',
            'reservation',
            'gagne',
        ]);
        $query = $filter->applyDateFilter($query, 'created_at');

        if ($filter->propertyId) {
            $query->where('property_id', $filter->propertyId);
        }
        if ($filter->assignedTo) {
            $query->where('assigned_to', $filter->assignedTo);
        }

        return $query->count();
    }

    public function getOffersCount(AnalyticsFilterData $filter): int
    {
        // Offres en cours ou étapes ultérieures
        $query = Opportunity::query()->whereIn('stage', [
            'offre_en_cours',
            'reservation',
            'gagne',
        ]);
        $query = $filter->applyDateFilter($query, 'created_at');

        if ($filter->propertyId) {
            $query->where('property_id', $filter->propertyId);
        }
        if ($filter->assignedTo) {
            $query->where('assigned_to', $filter->assignedTo);
        }

        return $query->count();
    }

    public function getReservationsCount(AnalyticsFilterData $filter): int
    {
        // Réservations actives (exclut les pures annulations)
        $query = Reservation::query()->whereIn('status', ['option', 'confirmed', 'completed']);
        $query = $filter->applyDateFilter($query, 'created_at');

        if ($filter->propertyId) {
            $query->where('property_id', $filter->propertyId);
        }
        if ($filter->assignedTo) {
            $query->where('assigned_to', $filter->assignedTo);
        }

        return $query->count();
    }

    public function getSignedContractsCount(AnalyticsFilterData $filter): int
    {
        $query = Contract::query()->where('status', 'signed');
        $query = $filter->applyDateFilter($query, 'contracts.created_at');

        if ($filter->propertyId || $filter->assignedTo) {
            $query->whereHas('reservation', function ($q) use ($filter) {
                if ($filter->propertyId) {
                    $q->where('property_id', $filter->propertyId);
                }
                if ($filter->assignedTo) {
                    $q->where('assigned_to', $filter->assignedTo);
                }
            });
        }

        return $query->count();
    }

    public function getAll(AnalyticsFilterData $filter): array
    {
        return [
            'prospects' => $this->getProspectsCount($filter),
            'qualifies' => $this->getQualifiedProspectsCount($filter),
            'opportunites' => $this->getOpportunitiesCount($filter),
            'visites' => $this->getVisitsCount($filter),
            'offres' => $this->getOffersCount($filter),
            'reservations' => $this->getReservationsCount($filter),
            'signatures' => $this->getSignedContractsCount($filter),
        ];
    }
}
