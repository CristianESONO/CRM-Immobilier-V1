<?php

namespace App\Jobs;

use App\Models\PaymentReminder;
use App\Services\Notifications\NotificationManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendPaymentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $reminderId;

    public function __construct(int $reminderId)
    {
        $this->reminderId = $reminderId;
    }

    public function handle(NotificationManager $manager): void
    {
        $reminder = PaymentReminder::withoutGlobalScope(\App\Scopes\TenantScope::class)->find($this->reminderId);

        if (!$reminder || in_array($reminder->status, ['sent', 'delivered'])) {
            return;
        }

        // Passage à l'état sending
        $reminder->update([
            'status' => 'sending',
            'last_attempt_at' => now(),
        ]);

        try {
            $success = $manager->send($reminder);

            if ($success) {
                $reminder->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null,
                ]);
            } else {
                $reminder->increment('retry_count');
                $reminder->update([
                    'status' => 'failed',
                    'error_message' => 'Échec retourné par le provider externe de notification.',
                ]);
            }
        } catch (Throwable $e) {
            $reminder->increment('retry_count');
            $reminder->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
