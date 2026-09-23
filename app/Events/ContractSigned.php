<?php

namespace App\Events;

use App\Contracts\DomainEventInterface;
use App\Events\Concerns\HasEventMetadata;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContractSigned implements DomainEventInterface
{
    use Dispatchable, SerializesModels, HasEventMetadata;

    public function __construct(
        public Contract $contract,
        public ?string $signedPdfPath = null,
        public ?User $user = null
    ) {
        $this->initializeEventMetadata();
    }
}
