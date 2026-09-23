<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationProviderInterface;
use App\Models\PaymentReminder;
use InvalidArgumentException;

class NotificationManager
{
    /**
     * Résout le provider selon le canal demandé.
     */
    public function getProvider(string $channel): NotificationProviderInterface
    {
        return match ($channel) {
            'whatsapp' => new WhatsAppProvider(),
            'email' => new EmailProvider(),
            'sms' => new SmsProvider(),
            default => new WhatsAppProvider(),
        };
    }

    /**
     * Déclenche l'envoi technique via le provider approprié.
     */
    public function send(PaymentReminder $reminder): bool
    {
        $provider = $this->getProvider($reminder->channel);
        return $provider->send($reminder);
    }
}
