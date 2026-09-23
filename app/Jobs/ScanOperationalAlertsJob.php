<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Alerts\OperationalAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScanOperationalAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected ?int $tenantId = null) {}

    public function handle(OperationalAlertService $alertService): void
    {
        if ($this->tenantId) {
            $tenant = Tenant::find($this->tenantId);
            if ($tenant) {
                $alertService->scanTenant($tenant);
            }
        } else {
            $alertService->scanAllTenants();
        }
    }
}
