<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

final class NotificationRequest
{
    public function __construct(
        public readonly NotificationType $type,
        public readonly NotificationRecipient $recipient,
        public readonly array $data = [],
        public readonly NotificationPriority $priority = NotificationPriority::NORMAL,
        public readonly ?NotificationChannel $preferredChannel = null,
        public readonly ?NotificationChannel $requiredChannel = null,
        public readonly array $metadata = []
    ) {}
}
