<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Unit;
use Illuminate\Support\Collection;

class PropertyMatchingService
{
    /**
     * Find matching units for a specific contact based on their real estate criteria.
     *
     * @param Contact $contact
     * @param float $budgetTolerancePercent e.g. 0.10 for +10% above budget_max
     * @return Collection
     */
    public function findMatchingUnitsForContact(Contact $contact, float $budgetTolerancePercent = 0.10): Collection
    {
        // Load available units with their parent property in the current tenant scope
        $units = Unit::with('property')
            ->where('status', 'available')
            ->get();

        $matchedResults = $units->map(function (Unit $unit) use ($contact, $budgetTolerancePercent) {
            $criteria = [];
            $reasons = [];
            $scorePoints = 0;
            $maxPoints = 0;

            // 1. Availability check (pre-requisite, already filtered but recorded)
            $isAvailable = ($unit->status === 'available');
            $criteria['available'] = $isAvailable;

            // 2. Budget Check
            if ($contact->budget_max && $contact->budget_max > 0) {
                $maxPoints += 40;
                $allowedMax = $contact->budget_max * (1 + $budgetTolerancePercent);
                $unitPrice = (float) $unit->price;

                if ($unitPrice > 0 && $unitPrice <= $contact->budget_max) {
                    $scorePoints += 40;
                    $criteria['budget'] = true;
                    $reasons[] = sprintf(
                        'Prix (%s FCFA) parfaitement dans le budget max (%s FCFA)',
                        number_format($unitPrice, 0, ',', ' '),
                        number_format($contact->budget_max, 0, ',', ' ')
                    );
                } elseif ($unitPrice > 0 && $unitPrice <= $allowedMax) {
                    $scorePoints += 25;
                    $criteria['budget'] = 'tolerance';
                    $reasons[] = sprintf(
                        'Prix (%s FCFA) légèrement supérieur au budget max (+%.0f%%)',
                        number_format($unitPrice, 0, ',', ' '),
                        (($unitPrice - $contact->budget_max) / $contact->budget_max) * 100
                    );
                } else {
                    $criteria['budget'] = false;
                    $reasons[] = sprintf(
                        'Prix (%s FCFA) dépasse le budget max (%s FCFA)',
                        number_format($unitPrice, 0, ',', ' '),
                        number_format($contact->budget_max, 0, ',', ' ')
                    );
                }
            }

            // 3. Property Type Check
            if (!empty($contact->property_type)) {
                $maxPoints += 35;
                $propertyType = $unit->property?->property_type;
                if ($propertyType && strtolower($propertyType) === strtolower($contact->property_type)) {
                    $scorePoints += 35;
                    $criteria['type'] = true;
                    $reasons[] = sprintf('Type de bien correspondant (%s)', $contact->property_type);
                } else {
                    $criteria['type'] = false;
                    $reasons[] = sprintf('Type différent (%s vs %s souhaité)', $propertyType ?? 'Non spécifié', $contact->property_type);
                }
            }

            // 4. District / Location Check
            if (!empty($contact->district)) {
                $maxPoints += 25;
                $location = $unit->property?->location ?? '';
                if (stripos($location, $contact->district) !== false || stripos($contact->district, $location) !== false) {
                    $scorePoints += 25;
                    $criteria['district'] = true;
                    $reasons[] = sprintf('Localisation correspondant au secteur (%s)', $contact->district);
                } else {
                    $criteria['district'] = false;
                    $reasons[] = sprintf('Secteur (%s) éloigné de la recherche (%s)', $location ?: 'Non spécifié', $contact->district);
                }
            }

            // If no search criteria were defined, give a default baseline
            $finalScore = $maxPoints > 0 ? (int) round(($scorePoints / $maxPoints) * 100) : 50;

            return [
                'unit' => $unit,
                'property' => $unit->property,
                'score' => $finalScore,
                'criteria' => $criteria,
                'reasons' => $reasons,
                'is_compatible' => ($finalScore >= 50),
            ];
        });

        // Filter by compatibility and sort by descending score
        return $matchedResults
            ->filter(fn ($item) => $item['score'] >= 50)
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Find matching prospects for a given lot/unit.
     * Useful when a new unit is released or becomes available.
     *
     * @param Unit $unit
     * @return Collection
     */
    public function findMatchingContactsForUnit(Unit $unit): Collection
    {
        $contacts = Contact::whereNotNull('budget_max')
            ->orWhereNotNull('property_type')
            ->orWhereNotNull('district')
            ->get();

        $matchingContacts = $contacts->map(function (Contact $contact) use ($unit) {
            $match = $this->findMatchingUnitsForContact($contact);
            $lotMatch = $match->firstWhere(fn ($item) => $item['unit']->id === $unit->id);

            if ($lotMatch) {
                return [
                    'contact' => $contact,
                    'score' => $lotMatch['score'],
                    'reasons' => $lotMatch['reasons'],
                ];
            }

            return null;
        })->filter()->sortByDesc('score')->values();

        return $matchingContacts;
    }
}
