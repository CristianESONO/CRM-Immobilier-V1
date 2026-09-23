<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\PaymentReminderService;
use Illuminate\Console\Command;

class SendPaymentRemindersCommand extends Command
{
    protected $signature = 'crm:send-payment-reminders
                            {--tenant= : ID spécifique du tenant à traiter}
                            {--dry-run : Afficher les relances sans les enregistrer}';

    protected $description = 'Analyse les échéances VEFA et envoie automatiquement les relances (J-7 préventive, Jour J, J+7 retard, J+15 critique)';

    public function handle(PaymentReminderService $service): int
    {
        $this->info("=== Traitement des relances automatiques d'échéances VEFA ===");

        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn("[MODE SIMULATION - DRY RUN] Aucune relance ne sera enregistrée en base.");
        }

        $tenants = $tenantId ? Tenant::where('id', $tenantId)->get() : Tenant::all();

        $totalReminders = 0;

        foreach ($tenants as $tenant) {
            $this->line("Traitement du promoteur : {$tenant->name} (ID: {$tenant->id})...");

            $reminders = $service->evaluateAndGenerateReminders($tenant->id);

            if (empty($reminders)) {
                $this->comment("  -> Aucune relance requise aujourd'hui.");
                continue;
            }

            $tableRows = [];
            foreach ($reminders as $r) {
                $totalReminders++;
                $tableRows[] = [
                    $r->id,
                    $r->recipient,
                    $r->channel,
                    $r->trigger_type,
                    $r->metadata['program_name'] ?? '—',
                    $r->metadata['unit_ref'] ?? '—',
                    number_format($r->metadata['remaining_due'] ?? 0, 0, ',', ' ') . ' FCFA',
                ];
            }

            $this->table(
                ['ID', 'Destinataire', 'Canal', 'Déclencheur', 'Programme', 'Lot', 'Montant Dû'],
                $tableRows
            );
        }

        $this->info("Terminé. Total de relances générées : {$totalReminders}");

        return self::SUCCESS;
    }
}
