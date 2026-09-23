<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Enregistre un événement immuable dans le journal d'audit central.
     *
     * @param string $action Ex: RESERVATION_CREATED, PAYMENT_RECORDED, REFUND_REQUESTED
     * @param Model $model
     * @param array|null $newValues
     * @param array|null $oldValues
     * @param int|null $userId
     * @param int|null $tenantId
     * @return AuditLog
     */
    public static function log(
        string $action,
        Model $model,
        ?array $newValues = null,
        ?array $oldValues = null,
        ?int $userId = null,
        ?int $tenantId = null
    ): AuditLog {
        $resolvedUserId = $userId ?? Auth::id();
        $resolvedTenantId = $tenantId ?? ($model->tenant_id ?? (Auth::user()?->tenant_id ?? session('tenant_id')));

        return AuditLog::create([
            'tenant_id' => $resolvedTenantId,
            'user_id' => $resolvedUserId,
            'action' => strtoupper($action),
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
