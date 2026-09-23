<?php

namespace App\Events;

use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Refund $refund,
        public ?User $user = null
    ) {}
}
