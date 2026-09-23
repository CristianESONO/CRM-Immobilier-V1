<?php

namespace App\Services\Alerts\Detectors;

use App\Models\PaymentSchedule;
use App\Models\Refund;
use App\Models\Tenant;
use Carbon\Carbon;

class FinanceAlertDetector implements AlertDetectorInterface
{
    public function detect(Tenant $tenant): array
    {
        $alerts = [];
        $today = Carbon::today()->startOfDay();
        $in7Days = $today->copy()->addDays(7);

        // 1. Échéances échues (due_date < aujourd'hui et reste dû > 0)
        PaymentSchedule::where('tenant_id', $tenant->id)
            ->whereColumn('paid_amount', '<', 'expected_amount')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->with('reservation')
            ->each(function (PaymentSchedule $schedule) use ($tenant, $today, &$alerts) {
                $daysOverdue = (int) Carbon::parse($schedule->due_date)->diffInDays($today);
                $remaining = $schedule->remainingAmount();

                $alerts[] = [
                    'type' => 'finance_schedule_overdue',
                    'severity' => 'critical',
                    'entity_type' => PaymentSchedule::class,
                    'entity_id' => $schedule->id,
                    'assigned_to' => $schedule->reservation?->assigned_to,
                    'title' => "Échéance en retard de {$daysOverdue} j ({$schedule->label})",
                    'description' => "Reste dû : " . number_format($remaining, 0, ',', ' ') . " FCFA sur la réservation {$schedule->reservation?->reference}.",
                    'idempotency_key' => "{$tenant->id}:finance_schedule_overdue:" . PaymentSchedule::class . ":{$schedule->id}",
                    'metadata' => [
                        'schedule_label' => $schedule->label,
                        'due_date' => $schedule->due_date?->toDateString(),
                        'remaining_amount' => $remaining,
                        'days_overdue' => $daysOverdue,
                    ],
                ];
            });

        // 2. Échéances proches (due_date entre aujourd'hui et J+7 et reste dû > 0)
        PaymentSchedule::where('tenant_id', $tenant->id)
            ->whereColumn('paid_amount', '<', 'expected_amount')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today->toDateString())
            ->whereDate('due_date', '<=', $in7Days->toDateString())
            ->with('reservation')
            ->each(function (PaymentSchedule $schedule) use ($tenant, &$alerts) {
                $remaining = $schedule->remainingAmount();

                $alerts[] = [
                    'type' => 'finance_schedule_upcoming',
                    'severity' => 'info',
                    'entity_type' => PaymentSchedule::class,
                    'entity_id' => $schedule->id,
                    'assigned_to' => $schedule->reservation?->assigned_to,
                    'title' => "Échéance à venir ({$schedule->label})",
                    'description' => "Échéance le {$schedule->due_date?->format('d/m/Y')} de " . number_format($remaining, 0, ',', ' ') . " FCFA.",
                    'idempotency_key' => "{$tenant->id}:finance_schedule_upcoming:" . PaymentSchedule::class . ":{$schedule->id}",
                    'metadata' => [
                        'schedule_label' => $schedule->label,
                        'due_date' => $schedule->due_date?->toDateString(),
                        'remaining_amount' => $remaining,
                    ],
                ];
            });

        // 3. Remboursement bloqué (statut pending/approved/processing créé depuis > 5j)
        Refund::where('tenant_id', $tenant->id)
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->where('created_at', '<=', Carbon::now()->subDays(5))
            ->each(function (Refund $refund) use ($tenant, &$alerts) {
                $alerts[] = [
                    'type' => 'finance_refund_blocked',
                    'severity' => 'warning',
                    'entity_type' => Refund::class,
                    'entity_id' => $refund->id,
                    'assigned_to' => $refund->reservation?->assigned_to,
                    'title' => "Remboursement bloqué ({$refund->reference})",
                    'description' => "Le remboursement de " . number_format((float) $refund->amount, 0, ',', ' ') . " FCFA au statut {$refund->status} est en attente depuis > 5j.",
                    'idempotency_key' => "{$tenant->id}:finance_refund_blocked:" . Refund::class . ":{$refund->id}",
                    'metadata' => [
                        'reference' => $refund->reference,
                        'amount' => (float) $refund->amount,
                        'status' => $refund->status,
                        'requested_at' => $refund->requested_at?->toIso8601String(),
                    ],
                ];
            });

        return $alerts;
    }
}
