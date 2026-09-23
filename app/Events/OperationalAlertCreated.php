<?php

namespace App\Events;

use App\Models\OperationalAlert;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OperationalAlertCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public OperationalAlert $alert,
        public ?User $user = null
    ) {}
}
