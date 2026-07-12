<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Events;

interface DomainEvent
{
    public function envelope(): EventEnvelope;
}
