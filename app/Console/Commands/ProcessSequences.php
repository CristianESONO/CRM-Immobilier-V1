<?php

namespace App\Console\Commands;

use App\Models\MessageLog;
use App\Models\SequenceEnrollment;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessSequences extends Command
{
    protected $signature = 'sequences:process';
    protected $description = 'Process active sequence enrollments and execute due steps idempotently.';

    public function handle(WhatsAppService $whatsAppService): int
    {
        $activeEnrollments = SequenceEnrollment::withoutGlobalScopes()
            ->with(['contact', 'sequence'])
            ->where('status', 'active')
            ->get();

        $processedCount = 0;

        foreach ($activeEnrollments as $enrollment) {
            $contact = $enrollment->contact;
            $sequence = $enrollment->sequence;

            if (!$contact || !$sequence || !$sequence->is_active) {
                continue;
            }

            // Stop condition check: if prospect replied, stop immediately
            if ($contact->q_replied_at) {
                $enrollment->update([
                    'status' => 'stopped',
                    'stopped_at' => now(),
                    'stop_reason' => 'prospect_replied',
                ]);
                continue;
            }

            $steps = $sequence->steps ?? [];
            $currentStepIndex = $enrollment->current_step;

            if (!isset($steps[$currentStepIndex])) {
                $enrollment->update(['status' => 'completed']);
                continue;
            }

            $step = $steps[$currentStepIndex];
            $delayHours = $step['delay_hours'] ?? 0;
            $dueTime = Carbon::parse($enrollment->enrolled_at)->addHours($delayHours);

            if (now()->greaterThanOrEqualTo($dueTime)) {
                $channel = $step['channel'] ?? 'whatsapp';
                $template = $step['template'] ?? 'default_relance';

                // Check idempotency: check if message was already sent for this enrollment & step
                $alreadySent = MessageLog::withoutGlobalScopes()
                    ->where('contact_id', $contact->id)
                    ->where('template', $template)
                    ->where('created_at', '>=', $enrollment->enrolled_at)
                    ->exists();

                if (!$alreadySent) {
                    if ($channel === 'whatsapp') {
                        $whatsAppService->sendTemplate($contact, $template);
                    } else {
                        MessageLog::create([
                            'tenant_id' => $contact->tenant_id,
                            'contact_id' => $contact->id,
                            'channel' => $channel,
                            'template' => $template,
                            'status' => 'sent',
                            'sent_at' => now(),
                        ]);
                    }
                }

                $nextStepIndex = $currentStepIndex + 1;
                if ($nextStepIndex >= count($steps)) {
                    $enrollment->update([
                        'current_step' => $nextStepIndex,
                        'status' => 'completed',
                    ]);
                } else {
                    $nextStep = $steps[$nextStepIndex];
                    $nextRunAt = Carbon::parse($enrollment->enrolled_at)->addHours($nextStep['delay_hours'] ?? 0);
                    $enrollment->update([
                        'current_step' => $nextStepIndex,
                        'next_run_at' => $nextRunAt,
                    ]);
                }

                $processedCount++;
            }
        }

        $this->info("Processed {$processedCount} sequence steps.");
        return Command::SUCCESS;
    }
}
