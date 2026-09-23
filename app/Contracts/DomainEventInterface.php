<?php

namespace App\Contracts;

interface DomainEventInterface
{
    public function getEventId(): string;

    public function getCorrelationId(): string;

    public function getSchemaVersion(): string;
}
