<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

final class TransportProcessingResult
{
    /**
     * @param array<string, mixed>|null $outputPayload
     * @param array<string, mixed>|null $processedAckPayload
     */
    public function __construct(
        public readonly bool $success,
        public readonly bool $retryable = false,
        public readonly ?string $errorMessage = null,
        public readonly ?string $errorCode = null,
        public readonly ?array $outputPayload = null,
        public readonly ?array $processedAckPayload = null,
    ) {}

    /**
     * @param array<string, mixed>|null $outputPayload
     * @param array<string, mixed>|null $processedAckPayload
     */
    public static function success(?array $outputPayload = null, ?array $processedAckPayload = null): self
    {
        return new self(true, false, null, null, $outputPayload, $processedAckPayload);
    }

    public static function failure(string $errorMessage, bool $retryable = false, ?string $errorCode = null): self
    {
        return new self(false, $retryable, $errorMessage, $errorCode);
    }
}
