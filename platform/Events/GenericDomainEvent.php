<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Events;

final class GenericDomainEvent implements DomainEvent
{
    public function __construct(private readonly EventEnvelope $envelope) {}

    public function envelope(): EventEnvelope
    {
        return $this->envelope;
    }
}
