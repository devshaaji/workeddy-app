<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Outbound;

use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Identity\UuidGeneratorContract;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Transport\Shared\HeaderSanitizer;
use WorkEddy\Platform\Transport\TransportMessage;
use WorkEddy\Platform\Transport\TransportStoreInterface;
use Psr\Log\LoggerInterface;

final class TransportSender
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly TransportStoreInterface $store,
        private readonly UuidGeneratorContract $uuids,
        private readonly IClock $clock,
        private readonly ConfigLoader $config,
        private readonly HeaderSanitizer $sanitizer,
        ILoggerFactory $loggerFactory,
    ) {
        $this->logger = $loggerFactory->channel('transport');
    }

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
        $destination = $destination !== '' ? $destination : (string) $this->config->get('transport.default_destination', 'cloud.primary');
        if ($idempotencyKey !== null) {
            $existing = $this->store->findOutboxByIdempotency($destination, $topic, $idempotencyKey);
            if ($existing !== null) {
                $this->logger->info('Duplicate outgoing transport message ignored.', [
                    'destination' => $destination,
                    'topic' => $topic,
                    'idempotency_key' => $idempotencyKey,
                ]);

                return $existing;
            }
        }

        $now = $this->clock->now();
        $message = $this->store->createOutbox(new TransportMessage(
            null,
            $this->uuids->generate(),
            $destination,
            $topic,
            $payload,
            $this->sanitizer->sanitize($headers),
            $priority,
            TransportMessage::STATUS_PENDING,
            0,
            (int) $this->config->get('transport.retry.default_max_attempts', 10),
            $now,
            null,
            null,
            null,
            null,
            $idempotencyKey,
            $correlationId,
            $now,
            $now,
        ));

        $this->logger->info('Transport outbound message created.', [
            'message_uuid' => $message->uuid,
            'destination' => $message->destination,
            'topic' => $message->topic,
        ]);

        return $message;
    }
}
