<?php

namespace App\Services;

use App\Jobs\SendPaymentReminderJob;
use App\Models\PaymentReminder;
use App\Models\PaymentSchedule;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class PaymentReminderService
{
    /**
     * Analyse toutes les échéances actives et génère les relances requises selon les règles métier
     * en vérifiant la clé d'idempotence pour empêcher tout double envoi.
     *
     * @param int|null $tenantId
     * @return PaymentReminder[]
     */
    public function evaluateAndGenerateReminders(?int $tenantId = null): array
    {
        $today = Carbon::today();
        $generatedReminders = [];

        $query = PaymentSchedule::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->with([
                'reservation' => fn ($q) => $q->withoutGlobalScope(\App\Scopes\TenantScope::class),
                'reservation.contact' => fn ($q) => $q->withoutGlobalScope(\App\Scopes\TenantScope::class),
                'reservation.property' => fn ($q) => $q->withoutGlobalScope(\App\Scopes\TenantScope::class),
                'reservation.unit' => fn ($q) => $q->withoutGlobalScope(\App\Scopes\TenantScope::class),
            ])
            ->whereHas('reservation', function (Builder $q) {
                $q->withoutGlobalScope(\App\Scopes\TenantScope::class)
                    ->whereIn('status', ['option', 'confirmed']);
            })
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereRaw('expected_amount > paid_amount');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $schedules = $query->get();

        foreach ($schedules as $schedule) {
            if (!$schedule->due_date || !$schedule->reservation || !$schedule->reservation->contact) {
                continue;
            }

            $dueDate = Carbon::parse($schedule->due_date)->startOfDay();
            $diffInDays = $today->diffInDays($dueDate, false); // Positif si futur, négatif si passé

            $trigger = null;

            // Règle 1 : Préventive J-7 (entre J-7 et J-1 si non encore envoyé)
            if ($diffInDays > 0 && $diffInDays <= 7) {
                $hasJ7 = $schedule->reminders()
                    ->where('trigger_type', 'preventive_j7')
                    ->exists();

                if (!$hasJ7) {
                    $trigger = 'preventive_j7';
                }
            }
            // Règle 2 : Échéance Jour J
            elseif ($diffInDays === 0) {
                $hasDueToday = $schedule->reminders()
                    ->where('trigger_type', 'due_today')
                    ->exists();

                if (!$hasDueToday) {
                    $trigger = 'due_today';
                }
            }
            // Règle 3 : Retard J+7 (entre 7 et 14 jours de retard)
            elseif ($diffInDays <= -7 && $diffInDays > -15) {
                $hasOverdueJ7 = $schedule->reminders()
                    ->where('trigger_type', 'overdue_j7')
                    ->exists();

                if (!$hasOverdueJ7) {
                    $trigger = 'overdue_j7';
                }
            }
            // Règle 4 : Retard critique J+15 et plus
            elseif ($diffInDays <= -15) {
                // Relance tous les 7 jours après J+15
                $recentCriticalReminder = $schedule->reminders()
                    ->where('trigger_type', 'overdue_j15')
                    ->where('sent_at', '>=', now()->subDays(7))
                    ->exists();

                if (!$recentCriticalReminder) {
                    $trigger = 'overdue_j15';
                }
            }

            if ($trigger) {
                $idempotencyKey = sprintf('rem_%d_%d_%s_%s', $schedule->tenant_id, $schedule->id, $trigger, $today->toDateString());

                // Vérification stricte d'idempotence
                $alreadyExists = PaymentReminder::withoutGlobalScope(\App\Scopes\TenantScope::class)
                    ->where('tenant_id', $schedule->tenant_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                $contact = $schedule->reservation->contact;
                $phone = $contact->phone_e164 ?? $contact->phone;
                $channel = $phone ? 'whatsapp' : 'email';
                $reminder = $this->sendReminder(
                    schedule: $schedule,
                    triggerType: $trigger,
                    channel: $channel,
                    idempotencyKey: $idempotencyKey
                );
                $generatedReminders[] = $reminder;
            }
        }

        return $generatedReminders;
    }

    /**
     * Envoie et enregistre une relance (automatisée ou manuelle) via Queue Job.
     */
    public function sendReminder(
        PaymentSchedule $schedule,
        string $triggerType,
        string $channel = 'whatsapp',
        ?string $customMessage = null,
        ?int $userId = null,
        ?string $idempotencyKey = null
    ): PaymentReminder {
        $contact = $schedule->reservation->contact;
        $phone = $contact->phone_e164 ?? $contact->phone;
        $recipient = ($channel === 'email')
            ? ($contact->email ?? $phone ?? 'non-renseigné')
            : ($phone ?? $contact->email ?? 'non-renseigné');

        $messageContent = $customMessage ?: $this->generateMessageText($schedule, $triggerType, $channel);
        $subject = $this->generateSubject($schedule, $triggerType);

        if (!$idempotencyKey) {
            $idempotencyKey = sprintf('rem_%d_%d_%s_%s_%d', $schedule->tenant_id, $schedule->id, $triggerType, now()->toDateString(), (int) microtime(true));
        }

        // Si l'échéance est passée, s'assurer que le statut est 'overdue'
        if ($schedule->due_date && Carbon::parse($schedule->due_date)->isPast() && $schedule->status !== 'paid') {
            $schedule->update(['status' => 'overdue']);
        }

        $reminder = PaymentReminder::create([
            'tenant_id' => $schedule->tenant_id,
            'idempotency_key' => $idempotencyKey,
            'payment_schedule_id' => $schedule->id,
            'reservation_id' => $schedule->reservation_id,
            'contact_id' => $contact->id,
            'sent_by_user_id' => $userId,
            'trigger_type' => $triggerType,
            'channel' => $channel,
            'status' => 'pending',
            'recipient' => $recipient,
            'subject' => $subject,
            'message_content' => $messageContent,
            'metadata' => [
                'expected_amount' => $schedule->expected_amount,
                'remaining_due' => (float) ($schedule->expected_amount - $schedule->paid_amount),
                'due_date' => $schedule->due_date?->toDateString(),
                'program_name' => $schedule->reservation->property?->name,
                'unit_ref' => $schedule->reservation->unit?->reference,
            ],
        ]);

        // Découplage : transmission déléguée au Job asynchrone
        SendPaymentReminderJob::dispatch($reminder->id);

        return $reminder->fresh();
    }

    /**
     * Génère l'objet pour un message de relance.
     */
    public function generateSubject(PaymentSchedule $schedule, string $triggerType): string
    {
        $program = $schedule->reservation->property?->name ?? 'Votre Programme';
        $lot = $schedule->reservation->unit?->reference ?? 'Votre Lot';

        return match ($triggerType) {
            'preventive_j7' => "Rappel d'échéance à venir — {$program} (Lot {$lot})",
            'due_today' => "Échéance de paiement aujourd'hui — {$program} (Lot {$lot})",
            'overdue_j7' => "Rappel : Échéance en attente de règlement — {$program} (Lot {$lot})",
            'overdue_j15' => "URGENT : Retard de paiement constaté — {$program} (Lot {$lot})",
            default => "Suivi de votre dossier de réservation — {$program} (Lot {$lot})",
        };
    }

    /**
     * Génère le corps du message adapté au type de déclencheur et au canal.
     */
    public function generateMessageText(PaymentSchedule $schedule, string $triggerType, string $channel = 'whatsapp'): string
    {
        $contact = $schedule->reservation->contact;
        $name = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? 'Client'));
        $program = $schedule->reservation->property?->name ?? 'votre programme';
        $lot = $schedule->reservation->unit?->reference ?? 'votre lot';
        $ref = $schedule->reservation->reference;
        $due = $schedule->due_date ? Carbon::parse($schedule->due_date)->format('d/m/Y') : 'inconnue';
        $remaining = number_format(max(0, $schedule->expected_amount - $schedule->paid_amount), 0, ',', ' ') . ' FCFA';
        $label = $schedule->label;

        if ($triggerType === 'preventive_j7') {
            return "Bonjour {$name},\n\nNous vous informons que l'échéance « {$label} » pour votre réservation {$ref} ({$program} - Lot {$lot}) arrive à échéance le {$due}.\n\nMontant attendu : {$remaining}.\n\nPour toute question ou transmission de votre justificatif de virement, votre conseiller reste à votre entière disposition.\n\nCordialement,\nService Gestion & Financement.";
        }

        if ($triggerType === 'due_today') {
            return "Bonjour {$name},\n\nVotre échéance « {$label} » relative au lot {$lot} ({$program}) arrive à échéance aujourd'hui {$due}.\n\nMontant à régulariser : {$remaining}.\n\nMerci de bien vouloir nous transmettre votre preuve de règlement dès transmission à votre banque.\n\nBien cordialement,\nService Relation Client.";
        }

        if ($triggerType === 'overdue_j7') {
            return "Bonjour {$name},\n\nSauf erreur de notre part, l'appel de fonds « {$label} » d'un montant de {$remaining}, prévu au {$due} pour votre lot {$lot} ({$program}), n'a pas encore été enregistré.\n\nNous vous remercions de bien vouloir régulariser cette situation ou contacter votre conseiller pour faire le point sur votre dossier.\n\nCordialement,\nService Comptabilité & Recouvrement.";
        }

        if ($triggerType === 'overdue_j15') {
            return "IMPORTANT — À l'attention de M./Mme {$name},\n\nDossier Réf : {$ref} ({$program} - Lot {$lot}).\nMalgré nos précédentes relances, l'échéance « {$label} » du {$due} reste impayée à ce jour pour un montant de {$remaining}.\n\nNous vous prions de procéder au versement sous 48h ou de vous rapprocher d'urgence de la direction commerciale afin d'éviter la suspension des options contractuelles.\n\nService Recouvrement.";
        }

        // Manuel / custom
        return "Bonjour {$name},\n\nConcernant votre dossier de réservation {$ref} pour le lot {$lot} au sein du programme {$program} :\nNous vous remercions pour votre confiance et restons à votre disposition pour le suivi de votre échéance « {$label} » ({$remaining}).\n\nBien cordialement,\nVotre conseiller commercial.";
    }
}
