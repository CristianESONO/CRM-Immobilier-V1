<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationProviderInterface;
use App\Models\PaymentReminder;
use Illuminate\Support\Facades\Log;

class EmailProvider implements NotificationProviderInterface
{
    public function getName(): string
    {
        return 'SMTP / Postmark / SendGrid Provider';
    }

    public function send(PaymentReminder $reminder): bool
    {
        Log::info(sprintf(
            "[Email Provider] Email expédié à %s (Objet: %s, Relance #%d)",
            $reminder->recipient,
            $reminder->subject,
            $reminder->id
        ), [
            'content' => $reminder->message_content,
        ]);

        return true;
    }
}
