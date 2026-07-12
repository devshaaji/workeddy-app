<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound\Adapters;

use WorkEddy\Platform\Transport\Inbound\TransportReceiveResult;
use WorkEddy\Platform\Transport\Inbound\TransportReceiverService;

final class DatabaseInboundAdapter
{
    public function __construct(private readonly TransportReceiverService $receiver) {}

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     */
    public function receive(string $source, string $topic, array $payload, array $headers = []): TransportReceiveResult
    {
        return $this->receiver->receive($source, $topic, $payload, $headers, null, null, null, [
            'source' => $source,
            'topic' => $topic,
            'payload' => $payload,
        ], false, false);
    }
}
