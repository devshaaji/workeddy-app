<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport;

final class TransportInboxMessage
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly string $source,
        public readonly string $topic,
        public readonly array $payload,
        public readonly string $status,
        public readonly \DateTimeImmutable $receivedAt,
        public readonly ?\DateTimeImmutable $processedAt,
        public readonly ?string $idempotencyKey,
        public readonly ?string $correlationId,
        public readonly ?string $errorMessage,
    ) {}
}
