<?php

namespace App\Events;

use App\Contracts\DomainEventInterface;
use App\Events\Concerns\HasEventMetadata;
use App\Models\BuyerDocument;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KycDocumentVerified implements DomainEventInterface
{
    use Dispatchable, SerializesModels, HasEventMetadata;

    public function __construct(
        public BuyerDocument $buyerDocument,
        public ?User $user = null
    ) {
        $this->initializeEventMetadata();
    }
}
