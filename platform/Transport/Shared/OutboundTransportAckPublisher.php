<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Shared;

use WorkEddy\Platform\Transport\Inbound\TransportInboxMessage;
use WorkEddy\Platform\Transport\Inbound\TransportProcessingResult;
use WorkEddy\Platform\Transport\Outbound\TransportSender;

final class OutboundTransportAckPublisher implements TransportAckPublisherInterface
{
    public function __construct(private readonly TransportSender $sender) {}

    public function publishProcessedAck(TransportInboxMessage $message, TransportProcessingResult $result): void
    {
        $this->sender->send(
            destination: $message->source,
            topic: 'transport.inbox.processed',
            payload: $result->processedAckPayload ?? [
                'inbox_uuid' => $message->uuid,
                'topic' => $message->topic,
                'success' => $result->success,
                'error_code' => $result->errorCode,
            ],
            headers: [],
            idempotencyKey: 'processed-ack-' . $message->uuid,
            correlationId: $message->correlationId,
        );
    }
}
