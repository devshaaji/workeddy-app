<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport;

use WorkEddy\Platform\Transport\Inbound\TransportInboxMessage;
use WorkEddy\Platform\Transport\Inbound\TransportReceiverService;
use WorkEddy\Platform\Transport\Outbound\TransportSender;

final class TransportService
{
    public function __construct(
        private readonly TransportSender $sender,
        private readonly ?TransportReceiverService $receiver = null,
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     */
    public function send(
        string $destination,
        string $topic,
        array $payload,
        array $headers = [],
        ?string $idempotencyKey = null,
        ?string $correlationId = null,
        string $priority = 'normal',
    ): TransportMessage {
        return $this->sender->send($destination, $topic, $payload, $headers, $idempotencyKey, $correlationId, $priority);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function receive(
        string $source,
        string $topic,
        array $payload,
        ?string $idempotencyKey = null,
        ?string $correlationId = null,
    ): TransportInboxMessage {
        if ($this->receiver === null) {
            throw new \RuntimeException('Transport inbound receiver is not configured.');
        }

        return $this->receiver->receive($source, $topic, $payload, [], $idempotencyKey, $correlationId)->message;
    }
}
