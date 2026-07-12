<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Events;

interface EventPublisherInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $eventName, array $payload, string $idempotencyKey): void;
}
