<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport;

final class TransportMessage
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RETRYING = 'retrying';

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly string $destination,
        public readonly string $topic,
        public readonly array $payload,
        public readonly array $headers,
        public readonly string $priority,
        public readonly string $status,
        public readonly int $attemptCount,
        public readonly int $maxAttempts,
        public readonly ?\DateTimeImmutable $nextAttemptAt,
        public readonly ?\DateTimeImmutable $lastAttemptAt,
        public readonly ?\DateTimeImmutable $deliveredAt,
        public readonly ?\DateTimeImmutable $failedAt,
        public readonly ?string $errorMessage,
        public readonly ?string $idempotencyKey,
        public readonly ?string $correlationId,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}
}
