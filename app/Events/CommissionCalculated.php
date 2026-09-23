<?php

namespace App\Events;

use App\Models\Commission;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommissionCalculated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Commission $commission,
        public ?User $user = null
    ) {}
}
