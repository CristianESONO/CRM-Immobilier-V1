<?php

namespace App\Services\Partners;

use App\Models\Contact;
use App\Models\Referrer;
use Carbon\Carbon;

class PartnerAttributionService
{
    /**
     * Check if a contact is within the valid 90-day referrer attribution window.
     */
    public function isAttributionValid(Contact $contact, int $attributionDaysWindow = 90): bool
    {
        if (!$contact->referrer_id) {
            return false;
        }

        $createdAt = $contact->created_at ?? Carbon::now();
        $daysPassed = (int) $createdAt->diffInDays(Carbon::now());

        return $daysPassed <= $attributionDaysWindow;
    }

    /**
     * Link a lead/contact to a referrer with validation.
     */
    public function attributeLead(Contact $contact, Referrer $referrer): Contact
    {
        $contact->update([
            'referrer_id' => $referrer->id,
        ]);

        return $contact;
    }
}
