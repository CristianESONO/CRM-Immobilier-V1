<?php

namespace App\Contracts;

use App\Models\PaymentReminder;

interface NotificationProviderInterface
{
    /**
     * Envoie la notification de relance via le fournisseur externe.
     *
     * @param PaymentReminder $reminder
     * @return bool  True si l'envoi est confirmé, False en cas d'échec
     */
    public function send(PaymentReminder $reminder): bool;

    /**
     * Nom d'identification du fournisseur (ex: Meta Cloud API, Twilio, Sendgrid...).
     */
    public function getName(): string;
}
