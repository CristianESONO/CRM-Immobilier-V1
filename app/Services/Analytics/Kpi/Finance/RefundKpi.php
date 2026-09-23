<?php

namespace App\Services\Analytics\Kpi\Finance;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\DTOs\Analytics\RefundOverviewData;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Builder;

/**
 * V4.3 — Remboursements (lecture seule).
 *
 * Seul status=completed (processed_at) est une sortie de trésorerie réelle.
 */
class RefundKpi
{
    public function getOverview(AnalyticsFilterData $filter, float $grossCollected): RefundOverviewData
    {
        $byStatus = [
            'pending' => ['amount' => 0.0, 'count' => 0],
            'approved' => ['amount' => 0.0, 'count' => 0],
            'processing' => ['amount' => 0.0, 'count' => 0],
            'completed' => ['amount' => 0.0, 'count' => 0],
            'rejected' => ['amount' => 0.0, 'count' => 0],
        ];

        $this->baseQuery($filter)
            ->get(['status', 'amount'])
            ->each(function (Refund $refund) use (&$byStatus) {
                $status = $refund->status;
                if (!isset($byStatus[$status])) {
                    return;
                }
                $byStatus[$status]['amount'] += (float) $refund->amount;
                $byStatus[$status]['count']++;
            });

        $executed = $byStatus['completed']['amount'];

        return new RefundOverviewData(
            requestedAmount: $byStatus['pending']['amount'],
            approvedAmount: $byStatus['approved']['amount'],
            processingAmount: $byStatus['processing']['amount'],
            executedAmount: $executed,
            rejectedAmount: $byStatus['rejected']['amount'],
            requestedCount: $byStatus['pending']['count'],
            approvedCount: $byStatus['approved']['count'],
            processingCount: $byStatus['processing']['count'],
            executedCount: $byStatus['completed']['count'],
            rejectedCount: $byStatus['rejected']['count'],
            grossCollected: $grossCollected,
            netCollected: max(0.0, $grossCollected - $executed),
        );
    }

    public function getExecutedInPeriod(AnalyticsFilterData $filter): float
    {
        $query = $this->baseQuery($filter)->where('status', 'completed');
        $query = $filter->applyDateFilter($query, 'processed_at');

        return (float) $query->sum('amount');
    }

    protected function baseQuery(AnalyticsFilterData $filter): Builder
    {
        $query = Refund::query();

        if ($filter->propertyId || $filter->assignedTo) {
            $query->whereHas('reservation', function (Builder $q) use ($filter) {
                if ($filter->propertyId) {
                    $q->where('property_id', $filter->propertyId);
                }
                if ($filter->assignedTo) {
                    $q->where('assigned_to', $filter->assignedTo);
                }
            });
        }

        return $query;
    }
}
