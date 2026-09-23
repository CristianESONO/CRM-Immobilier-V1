<?php

namespace App\Services\Governance;

use App\Models\Contact;
use App\Models\Contract;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Tenant;

class DataGovernanceService
{
    public function anonymizeContact(Contact $contact): Contact
    {
        $contact->update([
            'first_name' => 'Anonyme',
            'last_name' => 'Client #' . $contact->id,
            'email' => 'anonymized_' . $contact->id . '@gdpr-delete.invalid',
            'phone' => null,
            'address' => null,
            'notes' => 'Données PII anonymisées à la demande du client (RGPD)',
        ]);

        return $contact;
    }

    public function exportTenantData(Tenant $tenant): array
    {
        $properties = Property::where('tenant_id', $tenant->id)->with('units')->get();
        $contactsCount = Contact::where('tenant_id', $tenant->id)->count();
        $reservationsCount = Reservation::where('tenant_id', $tenant->id)->count();
        $contractsCount = Contract::where('tenant_id', $tenant->id)->count();

        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'plan' => $tenant->plan ?? 'pro',
                'created_at' => $tenant->created_at?->toIso8601String(),
            ],
            'summary' => [
                'properties_count' => $properties->count(),
                'units_count' => $properties->pluck('units')->flatten()->count(),
                'contacts_count' => $contactsCount,
                'reservations_count' => $reservationsCount,
                'contracts_count' => $contractsCount,
            ],
            'exported_at' => now()->toIso8601String(),
        ];
    }
}
