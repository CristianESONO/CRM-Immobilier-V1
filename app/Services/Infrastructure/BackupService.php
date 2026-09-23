<?php

namespace App\Services\Infrastructure;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BackupService
{
    /**
     * Create a structured JSON backup snapshot of a tenant's database records.
     *
     * @return array{success: bool, backup_key: string, checksum: string, record_counts: array}
     */
    public function createTenantBackup(Tenant $tenant): array
    {
        $tenantId = $tenant->id;

        $snapshot = [
            'tenant' => $tenant->toArray(),
            'users' => DB::table('users')->where('tenant_id', $tenantId)->get()->toArray(),
            'sources' => DB::table('sources')->where('tenant_id', $tenantId)->get()->toArray(),
            'properties' => DB::table('properties')->where('tenant_id', $tenantId)->get()->toArray(),
            'units' => DB::table('units')->where('tenant_id', $tenantId)->get()->toArray(),
            'contacts' => DB::table('contacts')->where('tenant_id', $tenantId)->get()->toArray(),
            'reservations' => DB::table('reservations')->where('tenant_id', $tenantId)->get()->toArray(),
            'payment_schedules' => DB::table('payment_schedules')->where('tenant_id', $tenantId)->get()->toArray(),
            'payments' => DB::table('payments')->where('tenant_id', $tenantId)->get()->toArray(),
            'refunds' => DB::table('refunds')->where('tenant_id', $tenantId)->get()->toArray(),
            'contracts' => DB::table('contracts')->where('tenant_id', $tenantId)->get()->toArray(),
            'buyer_documents' => DB::table('buyer_documents')->where('tenant_id', $tenantId)->get()->toArray(),
            'operational_alerts' => DB::table('operational_alerts')->where('tenant_id', $tenantId)->get()->toArray(),
            'workflows' => DB::table('workflows')->where('tenant_id', $tenantId)->get()->toArray(),
            'backup_timestamp' => now()->toIso8601String(),
        ];

        $jsonContent = json_encode($snapshot, JSON_PRETTY_PRINT);
        $checksum = hash('sha256', $jsonContent);
        $backupKey = "backups/tenant_{$tenantId}/backup_" . now()->format('Ymd_His') . ".json";

        Storage::disk('local')->put($backupKey, $jsonContent);

        $counts = [];
        foreach ($snapshot as $key => $records) {
            if (is_array($records)) {
                $counts[$key] = count($records);
            }
        }

        return [
            'success' => true,
            'backup_key' => $backupKey,
            'checksum' => $checksum,
            'record_counts' => $counts,
        ];
    }

    /**
     * Restore a tenant snapshot from private storage.
     */
    public function restoreTenantBackup(string $backupKey): bool
    {
        try {
            if (!Storage::disk('local')->exists($backupKey)) {
                return false;
            }

            $jsonContent = Storage::disk('local')->get($backupKey);
            $data = json_decode($jsonContent, true);

            if (!$data || !isset($data['tenant']['id'])) {
                return false;
            }

            $tenantId = $data['tenant']['id'];

            DB::transaction(function () use ($data, $tenantId) {
                // Remove existing tenant data safely
                DB::table('operational_alerts')->where('tenant_id', $tenantId)->delete();
                DB::table('buyer_documents')->where('tenant_id', $tenantId)->delete();
                DB::table('contracts')->where('tenant_id', $tenantId)->delete();
                DB::table('refunds')->where('tenant_id', $tenantId)->delete();
                DB::table('payments')->where('tenant_id', $tenantId)->delete();
                DB::table('payment_schedules')->where('tenant_id', $tenantId)->delete();
                DB::table('reservations')->where('tenant_id', $tenantId)->delete();
                DB::table('units')->where('tenant_id', $tenantId)->delete();
                DB::table('properties')->where('tenant_id', $tenantId)->delete();
                DB::table('contacts')->where('tenant_id', $tenantId)->delete();

                // Re-insert backed up records
                if (!empty($data['contacts'])) {
                    DB::table('contacts')->insert(json_decode(json_encode($data['contacts']), true));
                }
                if (!empty($data['properties'])) {
                    DB::table('properties')->insert(json_decode(json_encode($data['properties']), true));
                }
                if (!empty($data['units'])) {
                    DB::table('units')->insert(json_decode(json_encode($data['units']), true));
                }
                if (!empty($data['reservations'])) {
                    DB::table('reservations')->insert(json_decode(json_encode($data['reservations']), true));
                }
                if (!empty($data['payment_schedules'])) {
                    DB::table('payment_schedules')->insert(json_decode(json_encode($data['payment_schedules']), true));
                }
                if (!empty($data['payments'])) {
                    DB::table('payments')->insert(json_decode(json_encode($data['payments']), true));
                }
            });

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
