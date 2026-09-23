<?php

namespace App\Events\Concerns;

use Illuminate\Support\Str;

trait HasEventMetadata
{
    public ?string $eventId = null;
    public ?string $correlationId = null;
    public string $schemaVersion = '1.0';

    public function initializeEventMetadata(?string $correlationId = null): void
    {
        $this->eventId = 'evt_' . (string) Str::uuid();
        $this->correlationId = $correlationId ?? ('corr_' . (string) Str::uuid());
    }

    public function getEventId(): string
    {
        if (!$this->eventId) {
            $this->eventId = 'evt_' . (string) Str::uuid();
        }
        return $this->eventId;
    }

    public function getCorrelationId(): string
    {
        if (!$this->correlationId) {
            $this->correlationId = 'corr_' . (string) Str::uuid();
        }
        return $this->correlationId;
    }

    public function getSchemaVersion(): string
    {
        return $this->schemaVersion;
    }
}
