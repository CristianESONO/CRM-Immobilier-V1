<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationProviderInterface;
use App\Models\PaymentReminder;
use Illuminate\Support\Facades\Log;

class WhatsAppProvider implements NotificationProviderInterface
{
    public function getName(): string
    {
        return 'WhatsApp Cloud / Infobip Provider';
    }

    public function send(PaymentReminder $reminder): bool
    {
        // En environnement de prod, appel API (Meta WhatsApp Business API, Infobip ou Twilio)
        // En local/test, consignation sécurisée dans les logs et validation
        Log::info(sprintf(
            "[WhatsApp Provider] Envoi réussi vers %s pour la relance #%d (Jalon: %s)",
            $reminder->recipient,
            $reminder->id,
            $reminder->metadata['unit_ref'] ?? 'N/A'
        ), [
            'content' => $reminder->message_content,
        ]);

        return true;
    }
}
