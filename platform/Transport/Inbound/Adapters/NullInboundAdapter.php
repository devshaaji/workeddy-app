<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound\Adapters;

use WorkEddy\Platform\Transport\Inbound\TransportReceiveResult;
use WorkEddy\Platform\Transport\Inbound\TransportReceiverService;

final class NullInboundAdapter
{
    public function __construct(private readonly TransportReceiverService $receiver) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function receiveMock(string $topic, array $payload): TransportReceiveResult
    {
        return $this->receiver->receive('mock.testing', $topic, $payload, [], null, null, null, null, false, false);
    }
}
