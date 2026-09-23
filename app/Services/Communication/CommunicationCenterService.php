<?php

namespace App\Services\Communication;

use App\Models\CommunicationLog;
use App\Models\CommunicationPreference;
use App\Models\Contact;
use Carbon\Carbon;

class CommunicationCenterService
{
    public function setPreference(Contact $contact, array $data): CommunicationPreference
    {
        return CommunicationPreference::updateOrCreate(
            [
                'tenant_id' => $contact->tenant_id,
                'contact_id' => $contact->id,
            ],
            [
                'preferred_channel' => $data['preferred_channel'] ?? 'email',
                'opt_in_transactional' => $data['opt_in_transactional'] ?? true,
                'opt_in_marketing' => $data['opt_in_marketing'] ?? false,
                'quiet_hours_start' => $data['quiet_hours_start'] ?? null,
                'quiet_hours_end' => $data['quiet_hours_end'] ?? null,
            ]
        );
    }

    public function send(
        Contact $contact,
        string $channel,
        string $subject,
        string $message,
        bool $isTransactional = true,
    ): CommunicationLog {
        $pref = CommunicationPreference::where('tenant_id', $contact->tenant_id)
            ->where('contact_id', $contact->id)
            ->first();

        $status = 'sent';

        if ($pref) {
            // 1. Check Opt-in
            if ($isTransactional && !$pref->opt_in_transactional) {
                $status = 'blocked_opt_out';
            } elseif (!$isTransactional && !$pref->opt_in_marketing) {
                $status = 'blocked_opt_out';
            }

            // 2. Check Quiet Hours
            if ($status === 'sent' && $pref->quiet_hours_start && $pref->quiet_hours_end) {
                $nowTime = Carbon::now()->format('H:i:s');
                if ($this->isInQuietHours($nowTime, $pref->quiet_hours_start, $pref->quiet_hours_end)) {
                    $status = 'blocked_quiet_hours';
                }
            }
        }

        return CommunicationLog::create([
            'tenant_id' => $contact->tenant_id,
            'contact_id' => $contact->id,
            'channel' => $channel,
            'subject' => $subject,
            'message' => $message,
            'status' => $status,
            'sent_at' => now(),
        ]);
    }

    protected function isInQuietHours(string $now, string $start, string $end): bool
    {
        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        }

        // Overnight interval e.g. 22:00 to 07:00
        return $now >= $start || $now <= $end;
    }
}
