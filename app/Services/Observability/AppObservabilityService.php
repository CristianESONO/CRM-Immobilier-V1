<?php

namespace App\Services\Observability;

use App\Models\Commission;
use App\Models\OperationalAlert;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppObservabilityService
{
    public function getMetrics(int $tenantId): array
    {
        $now = now();
        $oneHourAgo = (clone $now)->subHour();

        $reservationsLastHour = Reservation::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        $totalReservations = Reservation::where('tenant_id', $tenantId)->count();

        $totalPaymentsAmount = (float) Payment::where('tenant_id', $tenantId)
            ->sum('amount');

        $totalCommissionsPaid = (float) Commission::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->sum('commission_amount');

        $webhooksTotal = WebhookDelivery::where('tenant_id', $tenantId)->count();
        $webhooksDelivered = WebhookDelivery::where('tenant_id', $tenantId)->where('status', 'delivered')->count();
        $webhooksFailed = WebhookDelivery::where('tenant_id', $tenantId)->where('status', 'failed')->count();
        $webhooksPending = WebhookDelivery::where('tenant_id', $tenantId)->where('status', 'pending')->count();

        $successRate = $webhooksTotal > 0 ? round(($webhooksDelivered / $webhooksTotal) * 100, 2) : 100.00;

        $unresolvedAlerts = OperationalAlert::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $queueDepth = 0;
        if (Schema::hasTable('jobs')) {
            $queueDepth = DB::table('jobs')->count();
        }

        return [
            'status' => 'healthy',
            'timestamp' => $now->toIso8601String(),
            'tenant_id' => $tenantId,
            'business_metrics' => [
                'reservations_last_hour' => $reservationsLastHour,
                'total_reservations' => $totalReservations,
                'total_payments_amount' => $totalPaymentsAmount,
                'total_commissions_paid' => $totalCommissionsPaid,
            ],
            'integration_metrics' => [
                'webhooks_total' => $webhooksTotal,
                'webhooks_delivered' => $webhooksDelivered,
                'webhooks_failed' => $webhooksFailed,
                'webhooks_pending' => $webhooksPending,
                'webhook_success_rate' => $successRate,
            ],
            'technical_metrics' => [
                'unresolved_alerts' => $unresolvedAlerts,
                'queue_depth' => $queueDepth,
            ],
        ];
    }
}
