<?php

namespace App\Events;

use App\Contracts\DomainEventInterface;
use App\Events\Concerns\HasEventMetadata;
use App\Models\Commission;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommissionPaid implements DomainEventInterface
{
    use Dispatchable, SerializesModels, HasEventMetadata;

    public function __construct(
        public Commission $commission,
        public ?User $user = null
    ) {
        $this->initializeEventMetadata();
    }
}
