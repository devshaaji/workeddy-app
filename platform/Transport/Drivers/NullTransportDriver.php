<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Drivers;

use WorkEddy\Platform\Transport\TransportDestination;
use WorkEddy\Platform\Transport\TransportDriverInterface;
use WorkEddy\Platform\Transport\TransportMessage;
use WorkEddy\Platform\Transport\TransportResult;

final class NullTransportDriver implements TransportDriverInterface
{
    public function send(TransportMessage $message, TransportDestination $destination): TransportResult
    {
        return TransportResult::success(204, null, 'null-' . $message->uuid, new \DateTimeImmutable());
    }

    public function isAvailable(TransportDestination $destination): bool
    {
        return $destination->enabled;
    }

    public function supports(string $driver): bool
    {
        return $driver === 'null' || $driver === 'mock';
    }
}
