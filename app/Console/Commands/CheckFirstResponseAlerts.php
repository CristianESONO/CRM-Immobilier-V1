<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Contact;
use App\Services\BusinessHours;
use Illuminate\Console\Command;

class CheckFirstResponseAlerts extends Command
{
    protected $signature = 'contacts:check-alerts';
    protected $description = 'Check contacts unresponded after 2 business hours and log SLA alerts.';

    public function handle(): int
    {
        $unrespondedContacts = Contact::withoutGlobalScopes()
            ->whereNull('first_response_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        $alertCount = 0;

        foreach ($unrespondedContacts as $contact) {
            $elapsedBusinessMinutes = BusinessHours::diffInMinutes($contact->created_at, now());

            if ($elapsedBusinessMinutes > 120) {
                // Check if an alert was already logged today for this contact
                $alreadyAlerted = Activity::withoutGlobalScopes()
                    ->where('contact_id', $contact->id)
                    ->where('type', 'alert')
                    ->where('created_at', '>=', now()->startOfDay())
                    ->exists();

                if (!$alreadyAlerted) {
                    Activity::create([
                        'tenant_id' => $contact->tenant_id,
                        'contact_id' => $contact->id,
                        'type' => 'alert',
                        'channel' => 'system',
                        'body' => "Dépassement du seuil de réponse 2h ({$elapsedBusinessMinutes} min d'heures ouvrées écoulées sans réponse).",
                        'occurred_at' => now(),
                    ]);

                    $alertCount++;
                }
            }
        }

        $this->info("Generated {$alertCount} first response SLA alerts.");
        return Command::SUCCESS;
    }
}
