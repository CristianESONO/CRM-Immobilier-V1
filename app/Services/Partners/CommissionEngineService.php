<?php

namespace App\Services\Partners;

use App\Events\CommissionCalculated;
use App\Events\CommissionPaid;
use App\Models\Commission;
use App\Models\CommissionHistory;
use App\Models\CommissionRule;
use App\Models\PartnerPortalAccess;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;

class CommissionEngineService
{
    public function calculateCommission(Reservation $reservation, ?User $user = null): ?Commission
    {
        $contact = $reservation->contact;
        if (!$contact || !$contact->referrer_id) {
            return null;
        }

        $referrerId = $contact->referrer_id;
        $tenantId = $reservation->tenant_id;
        $salesAmount = (float) $reservation->total_amount;

        // Find active rule or fallback to partner portal commission_rate
        $rule = CommissionRule::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        $rate = 2.50; // default 2.5%
        $ruleId = null;
        $ruleName = 'Taux Standard (2.5%)';

        if ($rule) {
            $rate = (float) $rule->rate;
            $ruleId = $rule->id;
            $ruleName = $rule->name;
        } else {
            $portalAccess = PartnerPortalAccess::where('tenant_id', $tenantId)
                ->where('referrer_id', $referrerId)
                ->first();
            if ($portalAccess) {
                $rate = (float) $portalAccess->commission_rate;
                $ruleName = 'Taux Personnalisé Portail (' . $rate . '%)';
            }
        }

        $commissionAmount = round(($salesAmount * $rate) / 100, 2);

        $commission = Commission::create([
            'tenant_id' => $tenantId,
            'referrer_id' => $referrerId,
            'reservation_id' => $reservation->id,
            'contact_id' => $contact->id,
            'rule_id' => $ruleId,
            'rate_snapshot' => $rate,
            'rule_name_snapshot' => $ruleName,
            'sales_amount' => $salesAmount,
            'commission_amount' => $commissionAmount,
            'status' => 'calculated',
            'calculated_at' => Carbon::now(),
        ]);

        CommissionHistory::create([
            'commission_id' => $commission->id,
            'from_status' => null,
            'to_status' => 'calculated',
            'user_id' => $user?->id,
            'reason' => 'Calcul automatique de commission',
            'changed_at' => Carbon::now(),
        ]);

        event(new CommissionCalculated($commission, $user));

        return $commission;
    }

    public function validateCommission(Commission $commission, ?User $user = null, ?string $reason = null): Commission
    {
        if ($commission->status === 'paid') {
            throw new \LogicException("Une commission à l'état 'paid' est immuable et ne peut pas être réévaluée.");
        }

        $oldStatus = $commission->status;
        $commission->update([
            'status' => 'validated',
            'validated_at' => Carbon::now(),
        ]);

        CommissionHistory::create([
            'commission_id' => $commission->id,
            'from_status' => $oldStatus,
            'to_status' => 'validated',
            'user_id' => $user?->id,
            'reason' => $reason ?? 'Validation administrative de la commission',
            'changed_at' => Carbon::now(),
        ]);

        return $commission;
    }

    public function markPayable(Commission $commission, ?User $user = null, ?string $reason = null): Commission
    {
        if ($commission->status === 'paid') {
            throw new \LogicException("Une commission à l'état 'paid' est immuable et ne peut pas être réévaluée.");
        }

        $oldStatus = $commission->status;
        $commission->update([
            'status' => 'payable',
            'payable_at' => Carbon::now(),
        ]);

        CommissionHistory::create([
            'commission_id' => $commission->id,
            'from_status' => $oldStatus,
            'to_status' => 'payable',
            'user_id' => $user?->id,
            'reason' => $reason ?? 'Éligibilité au paiement validée (premier acompte encaisse)',
            'changed_at' => Carbon::now(),
        ]);

        return $commission;
    }

    public function markPaid(Commission $commission, string $reference, ?User $user = null, ?string $reason = null, string $paymentMethod = 'bank_transfer'): Commission
    {
        if ($commission->status === 'paid') {
            throw new \LogicException("La commission #{$commission->id} a déjà été payée et est désormais immuable.");
        }

        $oldStatus = $commission->status;
        $commission->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
            'paid_by_user_id' => $user?->id,
            'payment_reference' => $reference,
            'payment_method' => $paymentMethod,
        ]);

        CommissionHistory::create([
            'commission_id' => $commission->id,
            'from_status' => $oldStatus,
            'to_status' => 'paid',
            'user_id' => $user?->id,
            'reason' => $reason ?? "Commission réglée. Réf virement : {$reference} via {$paymentMethod}",
            'changed_at' => Carbon::now(),
        ]);

        event(new CommissionPaid($commission, $user));

        return $commission;
    }
}
