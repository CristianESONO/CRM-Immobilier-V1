<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Source;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CrmPilotSeeder extends Seeder
{
    public function run(): void
    {
        // Pilot Tenant: GRET INVEST
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'gretinvest'],
            [
                'name' => 'GRET INVEST',
                'domain' => 'gretinvest.crm.linkup.sn',
                'settings' => [
                    'business_hours' => [
                        'days' => [1, 2, 3, 4, 5],
                        'start' => 8,
                        'end' => 18,
                        'timezone' => 'Africa/Dakar',
                    ],
                    'sla_first_response_hours' => 2,
                    'pipeline_stages' => [
                        'nouveau', 'contacte', 'qualifie', 'rdv_planifie',
                        'visite_planifiee', 'visite_realisee', 'proposition', 'gagne', 'perdu'
                    ],
                ]
            ]
        );

        session(['tenant_id' => $tenant->id]);

        // Pilot Users (4 Rôles du cahier des charges)
        User::firstOrCreate(
            ['email' => 'superadmin@linkup.sn'],
            [
                'name' => 'Super Admin LinkUp',
                'password' => Hash::make('Password123!'),
                'tenant_id' => null, // Cross-tenant
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@gretinvest.sn'],
            [
                'name' => 'Admin GRET INVEST',
                'password' => Hash::make('Password123!'),
                'tenant_id' => $tenant->id,
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'commercial@gretinvest.sn'],
            [
                'name' => 'Commercial GRET INVEST',
                'password' => Hash::make('Password123!'),
                'tenant_id' => $tenant->id,
                'role' => 'commercial',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'observer@agence-com.sn'],
            [
                'name' => 'Observateur Agence Com',
                'password' => Hash::make('Password123!'),
                'tenant_id' => $tenant->id,
                'role' => 'observer',
                'is_active' => true,
            ]
        );

        // Sources initiales
        $sources = [
            ['channel' => 'whatsapp', 'label' => 'WhatsApp Cloud API'],
            ['channel' => 'web', 'label' => 'Formulaire Web Landing Page'],
            ['channel' => 'referrer', 'label' => 'Apporteur d\'affaires / Prescripteur'],
            ['channel' => 'phone', 'label' => 'Appel entrant direct'],
            ['channel' => 'event', 'label' => 'Salon / Événement Immobilier'],
        ];

        foreach ($sources as $sourceData) {
            Source::firstOrCreate(
                ['tenant_id' => $tenant->id, 'label' => $sourceData['label']],
                ['channel' => $sourceData['channel'], 'is_active' => true]
            );
        }
    }
}
