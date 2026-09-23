<?php

namespace App\Services\Alerts;

use App\Models\OperationalAlert;
use App\Models\Tenant;
use App\Services\Alerts\Detectors\AlertDetectorInterface;
use App\Services\Alerts\Detectors\ContractAlertDetector;
use App\Services\Alerts\Detectors\FinanceAlertDetector;
use App\Services\Alerts\Detectors\KycAlertDetector;
use App\Services\Alerts\Detectors\SalesAlertDetector;
use App\Services\Alerts\Detectors\StockAlertDetector;
use App\Services\Alerts\Detectors\TechnicalAlertDetector;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OperationalAlertService
{
    /**
     * @var array<int, AlertDetectorInterface>
     */
    protected array $detectors;

    public function __construct(?array $detectors = null)
    {
        $this->detectors = $detectors ?? [
            new KycAlertDetector(),
            new ContractAlertDetector(),
            new FinanceAlertDetector(),
            new SalesAlertDetector(),
            new StockAlertDetector(),
            new TechnicalAlertDetector(),
        ];
    }

    /**
     * Scan all tenants for operational alerts.
     */
    public function scanAllTenants(): array
    {
        $summary = [
            'tenants_scanned' => 0,
            'alerts_created' => 0,
            'alerts_auto_resolved' => 0,
        ];

        Tenant::all()->each(function (Tenant $tenant) use (&$summary) {
            $res = $this->scanTenant($tenant);
            $summary['tenants_scanned']++;
            $summary['alerts_created'] += $res['created'];
            $summary['alerts_auto_resolved'] += $res['auto_resolved'];
        });

        return $summary;
    }

    /**
     * Scan a specific tenant.
     *
     * @return array{created: int, auto_resolved: int, total_open: int}
     */
    public function scanTenant(Tenant $tenant): array
    {
        $detectedSpecs = [];

        foreach ($this->detectors as $detector) {
            $specs = $detector->detect($tenant);
            foreach ($specs as $spec) {
                $detectedSpecs[$spec['idempotency_key']] = $spec;
            }
        }

        $created = 0;
        $now = Carbon::now();

        // 1. Process detected specifications
        foreach ($detectedSpecs as $key => $spec) {
            $existing = OperationalAlert::where('tenant_id', $tenant->id)
                ->where('idempotency_key', $key)
                ->first();

            if (!$existing) {
                OperationalAlert::create([
                    'tenant_id' => $tenant->id,
                    'type' => $spec['type'],
                    'severity' => $spec['severity'],
                    'status' => 'open',
                    'entity_type' => $spec['entity_type'],
                    'entity_id' => $spec['entity_id'],
                    'assigned_to' => $spec['assigned_to'] ?? null,
                    'title' => $spec['title'],
                    'description' => $spec['description'] ?? null,
                    'detected_at' => $now,
                    'idempotency_key' => $key,
                    'metadata' => $spec['metadata'] ?? null,
                ]);
                $created++;
            } elseif ($existing->status === 'resolved' && isset($spec['reopen']) && $spec['reopen'] === true) {
                $existing->update([
                    'status' => 'open',
                    'resolved_at' => null,
                    'detected_at' => $now,
                ]);
            }
        }

        // 2. Auto-resolve alerts whose condition no longer triggers
        $autoResolved = 0;
        $openAlerts = OperationalAlert::where('tenant_id', $tenant->id)
            ->open()
            ->get();

        foreach ($openAlerts as $alert) {
            if (!isset($detectedSpecs[$alert->idempotency_key])) {
                $alert->update([
                    'status' => 'resolved',
                    'resolved_at' => $now,
                ]);
                $autoResolved++;
            }
        }

        $totalOpen = OperationalAlert::where('tenant_id', $tenant->id)->open()->count();

        return [
            'created' => $created,
            'auto_resolved' => $autoResolved,
            'total_open' => $totalOpen,
        ];
    }

    public function acknowledge(OperationalAlert $alert): OperationalAlert
    {
        if ($alert->status === 'open') {
            $alert->update(['status' => 'acknowledged']);
        }

        return $alert;
    }

    public function resolve(OperationalAlert $alert, ?string $resolutionNotes = null): OperationalAlert
    {
        $meta = $alert->metadata ?? [];
        if ($resolutionNotes) {
            $meta['resolution_notes'] = $resolutionNotes;
        }

        $alert->update([
            'status' => 'resolved',
            'resolved_at' => Carbon::now(),
            'metadata' => $meta,
        ]);

        return $alert;
    }

    public function dismiss(OperationalAlert $alert, ?string $reason = null): OperationalAlert
    {
        $meta = $alert->metadata ?? [];
        if ($reason) {
            $meta['dismissal_reason'] = $reason;
        }

        $alert->update([
            'status' => 'dismissed',
            'metadata' => $meta,
        ]);

        return $alert;
    }

    /**
     * Summarize counts for dashboard widget.
     *
     * @return array{critical: int, warning: int, info: int, total_open: int, by_type: array<string, int>}
     */
    public function getSummaryForTenant(Tenant $tenant): array
    {
        $openQuery = OperationalAlert::where('tenant_id', $tenant->id)->open();

        $critical = (clone $openQuery)->where('severity', 'critical')->count();
        $warning = (clone $openQuery)->where('severity', 'warning')->count();
        $info = (clone $openQuery)->where('severity', 'info')->count();

        $byType = (clone $openQuery)
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->toArray();

        return [
            'critical' => $critical,
            'warning' => $warning,
            'info' => $info,
            'total_open' => $critical + $warning + $info,
            'by_type' => $byType,
        ];
    }
}
