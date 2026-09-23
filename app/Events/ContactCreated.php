<?php

namespace App\Events;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContactCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Contact $contact,
        public ?User $user = null
    ) {}
}
