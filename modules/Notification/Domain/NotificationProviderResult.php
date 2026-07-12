<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

final class NotificationProviderResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $errorMessage = null,
        public readonly ?\WorkEddy\Modules\Notification\Infrastructure\Clients\FailureType $failureType = null
    ) {}
}
