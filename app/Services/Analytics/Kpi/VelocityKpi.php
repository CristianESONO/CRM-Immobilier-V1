<?php

namespace App\Services\Analytics\Kpi;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Reservation;
use Carbon\Carbon;

class VelocityKpi
{
    /**
     * Délai moyen de première réponse SLA en heures.
     */
    public function getAverageFirstResponseHours(AnalyticsFilterData $filter): float
    {
        $query = Contact::query()->whereNotNull('first_response_minutes');
        $query = $filter->applyDateFilter($query, 'created_at');

        if ($filter->assignedTo) {
            $query->where('assigned_to', $filter->assignedTo);
        }

        $avgMinutes = (float) ($query->avg('first_response_minutes') ?? 0);

        return round($avgMinutes / 60, 1);
    }

    /**
     * Délai moyen entre la création du contact et sa qualification (en jours).
     */
    public function getAverageLeadToQualifiedDays(AnalyticsFilterData $filter): float
    {
        $query = Contact::query()->whereNotNull('qualified_at');
        $query = $filter->applyDateFilter($query, 'created_at');

        if ($filter->assignedTo) {
            $query->where('assigned_to', $filter->assignedTo);
        }

        $contacts = $query->select(['created_at', 'qualified_at'])->limit(200)->get();

        if ($contacts->isEmpty()) {
            return 0.0;
        }

        $totalDays = 0.0;
        foreach ($contacts as $contact) {
            $created = Carbon::parse($contact->created_at);
            $qualified = Carbon::parse($contact->qualified_at);
            $totalDays += max(0, $created->diffInHours($qualified) / 24);
        }

        return round($totalDays / $contacts->count(), 1);
    }

    /**
     * Délai moyen entre la qualification et la signature de réservation (en jours).
     */
    public function getAverageQualifiedToReservationDays(AnalyticsFilterData $filter): float
    {
        $query = Reservation::query()
            ->whereIn('status', ['option', 'confirmed', 'completed'])
            ->whereHas('contact', function ($q) {
                $q->whereNotNull('qualified_at');
            })
            ->with(['contact:id,qualified_at']);

        $query = $filter->applyDateFilter($query, 'created_at');

        if ($filter->propertyId) {
            $query->where('property_id', $filter->propertyId);
        }
        if ($filter->assignedTo) {
            $query->where('assigned_to', $filter->assignedTo);
        }

        $reservations = $query->select(['id', 'contact_id', 'created_at'])->limit(200)->get();

        if ($reservations->isEmpty()) {
            return 0.0;
        }

        $totalDays = 0.0;
        $count = 0;
        foreach ($reservations as $reservation) {
            if ($reservation->contact && $reservation->contact->qualified_at) {
                $qualified = Carbon::parse($reservation->contact->qualified_at);
                $reserved = Carbon::parse($reservation->created_at);
                $totalDays += max(0, $qualified->diffInHours($reserved) / 24);
                $count++;
            }
        }

        return $count > 0 ? round($totalDays / $count, 1) : 0.0;
    }

    /**
     * Délai moyen entre la réservation et la signature du contrat (en jours).
     */
    public function getAverageReservationToSignedDays(AnalyticsFilterData $filter): float
    {
        $query = Contract::query()
            ->where('status', 'signed')
            ->whereNotNull('signed_at')
            ->with(['reservation:id,created_at']);

        $query = $filter->applyDateFilter($query, 'contracts.created_at');

        $contracts = $query->select(['id', 'reservation_id', 'signed_at', 'created_at'])->limit(200)->get();

        if ($contracts->isEmpty()) {
            return 0.0;
        }

        $totalDays = 0.0;
        $count = 0;
        foreach ($contracts as $contract) {
            if ($contract->reservation && $contract->reservation->created_at) {
                $reserved = Carbon::parse($contract->reservation->created_at);
                $signed = Carbon::parse($contract->signed_at);
                $totalDays += max(0, $reserved->diffInHours($signed) / 24);
                $count++;
            }
        }

        return $count > 0 ? round($totalDays / $count, 1) : 0.0;
    }

    public function getAll(AnalyticsFilterData $filter): array
    {
        return [
            'average_first_response_hours' => $this->getAverageFirstResponseHours($filter),
            'average_lead_to_qualified_days' => $this->getAverageLeadToQualifiedDays($filter),
            'average_qualified_to_reservation_days' => $this->getAverageQualifiedToReservationDays($filter),
            'average_reservation_to_signed_days' => $this->getAverageReservationToSignedDays($filter),
        ];
    }
}
