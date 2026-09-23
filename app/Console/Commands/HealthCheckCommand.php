<?php

namespace App\Console\Commands;

use App\Services\Health\HealthCheckService;
use Illuminate\Console\Command;

class HealthCheckCommand extends Command
{
    protected $signature = 'crm:health-check {--json : Exporter le bilan de santé au format JSON brut}';

    protected $description = 'Exécute les sondes d\'observabilité et vérifie l\'intégrité de la plateforme CRM';

    public function handle(HealthCheckService $service): int
    {
        $report = $service->runAllChecks();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $report['status'] === 'healthy' ? 0 : 1;
        }

        $this->info("=================================================");
        $this->info("  BILAN D'OBSERVABILITÉ & SANTÉ CRM IMMOBILIER  ");
        $this->info("=================================================");
        $this->line("Environnement : <fg=cyan>{$report['app_environment']}</>");
        $this->line("Horodatage    : {$report['timestamp']}");
        $this->newLine();

        $rows = [];
        foreach ($report['checks'] as $component => $check) {
            $statusBadge = $check['status'] === 'ok'
                ? '<fg=green;options=bold>OK</>'
                : '<fg=red;options=bold>' . strtoupper($check['status']) . '</>';

            $rows[] = [
                ucfirst($component),
                $statusBadge,
                $check['message'] ?? '-',
            ];
        }

        $this->table(['Composant', 'Statut', 'Détails'], $rows);

        if ($report['status'] === 'healthy') {
            $this->info("\n✔ Tous les composants vitaux sont pleinement opérationnels.\n");
            return 0;
        }

        $this->warn("\n⚠ Certains composants nécessitent une attention opérationnelle.\n");
        return 1;
    }
}
