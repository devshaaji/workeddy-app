<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Drivers;

use WorkEddy\Platform\Transport\TransportDestination;
use WorkEddy\Platform\Transport\TransportDriverInterface;
use WorkEddy\Platform\Transport\TransportMessage;
use WorkEddy\Platform\Transport\TransportResult;

final class WebSocketTransportDriver implements TransportDriverInterface
{
    public function send(TransportMessage $message, TransportDestination $destination): TransportResult
    {
        return TransportResult::failure('WebSocket transport adapter is registered but no realtime server adapter is configured.', true);
    }

    public function isAvailable(TransportDestination $destination): bool
    {
        return false;
    }

    public function supports(string $driver): bool
    {
        return $driver === 'websocket' || $driver === 'ws';
    }
}
