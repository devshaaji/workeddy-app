<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

use WorkEddy\Modules\Notification\Infrastructure\Clients\FailureType;

final class NotificationDeliveryAttempt
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $logUuid,
        public readonly NotificationChannel $channel,
        public readonly string $providerKey,
        public readonly int $attemptCount,
        public readonly string $status,
        public readonly ?string $failureReason = null,
        public readonly ?FailureType $failureType = null,
        public readonly ?string $providerMessageId = null,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?int $id = null,
    ) {}
}
