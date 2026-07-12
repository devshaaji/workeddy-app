<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Clients;

final class ProviderSendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $provider,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $status = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?FailureType $failureType = null,
        public readonly mixed $rawResponse = null,
        public readonly ?\DateTimeImmutable $sentAt = null
    ) {}
}
