<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Identity\UuidGeneratorContract;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Transport\Shared\HeaderSanitizer;
use WorkEddy\Platform\Transport\Shared\PayloadSerializer;
use Psr\Log\LoggerInterface;

final class TransportReceiverService
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly TransportInboxRepository $inbox,
        private readonly InboundSourceValidator $validator,
        private readonly UuidGeneratorContract $uuids,
        private readonly IClock $clock,
        private readonly ConfigLoader $config,
        private readonly HeaderSanitizer $sanitizer,
        private readonly PayloadSerializer $serializer,
        ILoggerFactory $loggerFactory,
    ) {
        $this->logger = $loggerFactory->channel('transport');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     */
    public function receive(
        string $source,
        string $topic,
        array $payload,
        array $headers = [],
        ?string $idempotencyKey = null,
        ?string $correlationId = null,
        ?string $remoteMessageId = null,
        array|string|null $rawMessage = null,
        bool $receivedAckRequired = true,
        bool $processedAckRequired = false,
    ): TransportReceiveResult {
        $sanitizedHeaders = $this->sanitizer->sanitize($headers);
        $validation = $this->validator->validate($source, $topic, $headers, $payload);
        if (!$validation->valid) {
            $this->logger->warning('Inbound transport message rejected.', [
                'source' => $source,
                'topic' => $topic,
                'error_code' => $validation->errorCode,
            ]);

            return new TransportReceiveResult(false, false, true, null, null, TransportInboxMessage::STATUS_REJECTED, $validation->errorMessage);
        }

        $duplicate = $this->inbox->findDuplicate($source, $idempotencyKey, $remoteMessageId);
        if ($duplicate !== null) {
            $this->logger->info('Duplicate inbound transport message ignored.', [
                'source' => $source,
                'topic' => $topic,
                'idempotency_key' => $idempotencyKey,
                'remote_message_id' => $remoteMessageId,
            ]);

            return new TransportReceiveResult(true, true, false, $duplicate->id, $duplicate->uuid, TransportInboxMessage::STATUS_IGNORED_DUPLICATE, null, $duplicate);
        }

        $now = $this->clock->now();
        $message = $this->inbox->create(new TransportInboxMessage(
            null,
            $this->uuids->generate(),
            $source,
            $topic,
            $payload,
            $sanitizedHeaders,
            $this->serializer->encodeRaw($rawMessage),
            TransportInboxMessage::STATUS_RECEIVED,
            $idempotencyKey,
            $correlationId,
            $remoteMessageId,
            $receivedAckRequired,
            $processedAckRequired,
            $receivedAckRequired ? $now : null,
            null,
            0,
            (int) $this->config->get('transport_inbound.default_max_attempts', 10),
            $now,
            $now,
            null,
            null,
            null,
            null,
            null,
            $now,
            $now,
        ));

        $this->logger->info('Inbound transport message received.', [
            'inbox_uuid' => $message->uuid,
            'source' => $source,
            'topic' => $topic,
        ]);

        return new TransportReceiveResult(true, false, false, $message->id, $message->uuid, $message->status, null, $message);
    }
}
