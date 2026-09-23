<?php

namespace App\Events;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public string $reason,
        public ?User $user = null
    ) {}
}
