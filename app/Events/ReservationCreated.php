<?php

namespace App\Events;

use App\Contracts\DomainEventInterface;
use App\Events\Concerns\HasEventMetadata;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCreated implements DomainEventInterface
{
    use Dispatchable, SerializesModels, HasEventMetadata;

    public function __construct(
        public Reservation $reservation,
        public ?User $user = null
    ) {
        $this->initializeEventMetadata();
    }
}
