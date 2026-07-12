<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound\Adapters;

use WorkEddy\Platform\Transport\Inbound\TransportReceiveResult;
use WorkEddy\Platform\Transport\Inbound\TransportReceiverService;

final class PollingInboundAdapter
{
    public function __construct(private readonly TransportReceiverService $receiver) {}

    /**
     * @param list<array<string, mixed>> $messages
     * @return list<TransportReceiveResult>
     */
    public function receiveBatch(array $messages): array
    {
        $results = [];
        foreach ($messages as $message) {
            $payload = $message['payload'] ?? [];
            if (!is_array($payload)) {
                $payload = [];
            }
            $results[] = $this->receiver->receive(
                (string) ($message['source'] ?? ''),
                (string) ($message['topic'] ?? ''),
                $payload,
                is_array($message['headers'] ?? null) ? $message['headers'] : [],
                isset($message['idempotency_key']) ? (string) $message['idempotency_key'] : null,
                isset($message['correlation_id']) ? (string) $message['correlation_id'] : null,
                isset($message['remote_message_id']) ? (string) $message['remote_message_id'] : null,
                $message,
                (bool) ($message['received_ack_required'] ?? true),
                (bool) ($message['processed_ack_required'] ?? false),
            );
        }

        return $results;
    }
}
