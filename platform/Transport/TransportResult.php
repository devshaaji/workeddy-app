<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport;

final class TransportResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?int $statusCode = null,
        public readonly ?string $responseBody = null,
        public readonly ?string $remoteMessageId = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $retryable = false,
        public readonly ?\DateTimeImmutable $deliveredAt = null,
    ) {}

    public static function success(?int $statusCode = null, ?string $responseBody = null, ?string $remoteMessageId = null, ?\DateTimeImmutable $deliveredAt = null): self
    {
        return new self(true, $statusCode, $responseBody, $remoteMessageId, null, false, $deliveredAt);
    }

    public static function failure(string $errorMessage, bool $retryable = true, ?int $statusCode = null, ?string $responseBody = null): self
    {
        return new self(false, $statusCode, $responseBody, null, $errorMessage, $retryable, null);
    }
}
