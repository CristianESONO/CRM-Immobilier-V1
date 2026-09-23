<?php

namespace App\Console\Commands;

use App\Services\Alerts\OperationalAlertService;
use Illuminate\Console\Command;

class ScanOperationalAlertsCommand extends Command
{
    protected $signature = 'alerts:scan';

    protected $description = 'Scan domain databases for operational alerts across all tenants';

    public function handle(OperationalAlertService $alertService): int
    {
        $this->info('Starting operational alerts scan...');

        $summary = $alertService->scanAllTenants();

        $this->info("Scan complete: {$summary['tenants_scanned']} tenants scanned, {$summary['alerts_created']} alerts created, {$summary['alerts_auto_resolved']} alerts auto-resolved.");

        return Command::SUCCESS;
    }
}
