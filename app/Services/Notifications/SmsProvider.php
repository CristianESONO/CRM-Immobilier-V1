<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationProviderInterface;
use App\Models\PaymentReminder;
use Illuminate\Support\Facades\Log;

class SmsProvider implements NotificationProviderInterface
{
    public function getName(): string
    {
        return 'Orange SMS / Twilio Provider';
    }

    public function send(PaymentReminder $reminder): bool
    {
        Log::info(sprintf(
            "[SMS Provider] SMS envoyé au %s pour la relance #%d",
            $reminder->recipient,
            $reminder->id
        ), [
            'content' => $reminder->message_content,
        ]);

        return true;
    }
}
