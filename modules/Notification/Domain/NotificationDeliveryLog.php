<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

final class NotificationDeliveryLog
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $notificationType,
        public readonly string $recipientType,
        public readonly string $recipientId,
        public readonly NotificationChannel $channel,
        public readonly string $provider,
        public readonly string $status,
        public readonly ?string $subject = null,
        public readonly ?string $messagePreview = null,
        public readonly ?string $recipientName = null,
        public readonly ?string $recipientEmail = null,
        public readonly ?string $recipientPhone = null,
        public readonly int $attemptCount = 1,
        public readonly ?string $failureReason = null,
        public readonly ?\WorkEddy\Modules\Notification\Infrastructure\Clients\FailureType $failureType = null,
        public readonly ?string $providerMessageId = null,
        public readonly array $metadataJson = [],
        public readonly ?\DateTimeImmutable $queuedAt = null,
        public readonly ?\DateTimeImmutable $sentAt = null,
        public readonly ?\DateTimeImmutable $failedAt = null,
        public readonly ?int $id = null,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null
    ) {}
}
