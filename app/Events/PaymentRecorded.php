<?php

namespace App\Events;

use App\Contracts\DomainEventInterface;
use App\Events\Concerns\HasEventMetadata;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRecorded implements DomainEventInterface
{
    use Dispatchable, SerializesModels, HasEventMetadata;

    public function __construct(
        public Payment $payment,
        public ?User $user = null
    ) {
        $this->initializeEventMetadata();
    }
}
