<?php

namespace App\Services\Alerts\Detectors;

use App\Models\Tenant;

interface AlertDetectorInterface
{
    /**
     * Run detection for a given tenant and return array of alert specifications.
     *
     * @return array<int, array{
     *     type: string,
     *     severity: 'info'|'warning'|'critical',
     *     entity_type: string,
     *     entity_id: int,
     *     assigned_to: int|null,
     *     title: string,
     *     description: string|null,
     *     idempotency_key: string,
     *     metadata: array|null
     * }>
     */
    public function detect(Tenant $tenant): array;
}
