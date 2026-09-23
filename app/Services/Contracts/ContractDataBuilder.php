<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\Reservation;

class ContractDataBuilder
{
    /**
     * Fige un snapshot contractuel complet et immuable depuis la réservation.
     */
    public function buildSnapshot(Reservation $reservation, ?Contract $contract = null): array
    {
        $contact = $reservation->contact;
        $property = $reservation->property;
        $unit = $reservation->unit;
        $schedules = $reservation->schedules;

        $totalAmount = (float) $reservation->total_amount;
        $depositAmount = (float) $reservation->deposit_amount;

        $scheduleList = [];
        $scheduleRows = "";

        foreach ($schedules as $s) {
            $expected = (float) $s->expected_amount;
            $formattedExpected = number_format($expected, 0, ',', ' ') . ' FCFA';
            $dueDate = $s->due_date ? $s->due_date->format('d/m/Y') : 'À fixer';
            $percentage = $s->percentage ? $s->percentage . ' %' : '—';

            $scheduleList[] = [
                'label' => $s->label,
                'percentage' => $s->percentage,
                'expected_amount' => $expected,
                'expected_amount_formatted' => $formattedExpected,
                'due_date' => $dueDate,
            ];

            $scheduleRows .= "<tr><td>{$s->label}</td><td>{$percentage}</td><td>{$formattedExpected}</td><td>{$dueDate}</td></tr>";
        }

        $scheduleTable = "<table border='1' cellpadding='6' cellspacing='0' style='width:100%; border-collapse:collapse;'><thead><tr style='background:#f3f4f6;'><th>Jalon Contractuel</th><th>Quote-part</th><th>Montant Exigible</th><th>Date d’Appel</th></tr></thead><tbody>{$scheduleRows}</tbody></table>";

        return [
            'contract_number' => $contract?->contract_number ?? 'CTR-PROVISOIRE',
            'reservation_reference' => $reservation->reference,
            'generated_at' => now()->format('d/m/Y H:i'),
            'promoter' => [
                'name' => $property?->tenant?->name ?? 'Le Promoteur',
            ],
            'buyer' => [
                'id' => $contact->id,
                'full_name' => trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')),
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'phone' => $contact->phone_e164 ?? $contact->phone ?? 'Non renseigné',
                'email' => $contact->email ?? 'Non renseigné',
                'city' => $contact->city ?? 'Dakar',
                'country' => $contact->country ?? 'Sénégal',
            ],
            'program' => [
                'id' => $property->id,
                'name' => $property->name,
                'location' => $property->location,
                'property_type' => $property->property_type,
            ],
            'unit' => [
                'id' => $unit->id,
                'reference' => $unit->reference,
                'area' => (float) $unit->area,
                'price' => (float) $unit->price,
                'price_formatted' => number_format((float) $unit->price, 0, ',', ' ') . ' FCFA',
            ],
            'financials' => [
                'total_amount' => $totalAmount,
                'total_amount_formatted' => number_format($totalAmount, 0, ',', ' ') . ' FCFA',
                'deposit_amount' => $depositAmount,
                'deposit_amount_formatted' => number_format($depositAmount, 0, ',', ' ') . ' FCFA',
            ],
            'schedules' => $scheduleList,
            'payment_schedule_table' => $scheduleTable,
        ];
    }
}
