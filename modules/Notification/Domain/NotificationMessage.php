<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

final class NotificationMessage
{
    public function __construct(
        public readonly NotificationChannel $channel,
        public readonly NotificationRecipient $recipient,
        public readonly string $subject,
        public readonly string $body,
        public readonly bool $isHtml = false,
        public readonly array $metadata = []
    ) {}
}
