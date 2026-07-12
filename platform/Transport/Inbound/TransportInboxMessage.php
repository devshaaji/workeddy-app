<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

final class TransportInboxMessage
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RETRYING = 'retrying';
    public const STATUS_IGNORED_DUPLICATE = 'ignored_duplicate';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly string $source,
        public readonly string $topic,
        public readonly array $payload,
        public readonly array $headers,
        public readonly ?string $rawMessage,
        public readonly string $status,
        public readonly ?string $idempotencyKey,
        public readonly ?string $correlationId,
        public readonly ?string $remoteMessageId,
        public readonly bool $receivedAckRequired,
        public readonly bool $processedAckRequired,
        public readonly ?\DateTimeImmutable $receivedAckSentAt,
        public readonly ?\DateTimeImmutable $processedAckSentAt,
        public readonly int $attemptCount,
        public readonly int $maxAttempts,
        public readonly ?\DateTimeImmutable $nextAttemptAt,
        public readonly \DateTimeImmutable $receivedAt,
        public readonly ?\DateTimeImmutable $processingStartedAt,
        public readonly ?\DateTimeImmutable $processedAt,
        public readonly ?\DateTimeImmutable $failedAt,
        public readonly ?string $errorMessage,
        public readonly ?string $lastErrorCode,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}
}
