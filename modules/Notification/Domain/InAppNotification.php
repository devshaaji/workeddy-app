<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

final class InAppNotification
{
    /**
     * @param array<string, mixed>|null $metadataJson
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $recipientType,
        public readonly string $recipientId,
        public readonly string $notificationType,
        public readonly string $subject,
        public readonly string $body,
        public readonly ?array $metadataJson,
        public readonly ?\DateTimeImmutable $readAt = null,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null,
        public readonly ?int $id = null,
    ) {}
}
