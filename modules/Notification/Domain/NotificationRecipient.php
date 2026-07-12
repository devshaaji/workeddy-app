<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

final class NotificationRecipient
{
    public function __construct(
        public readonly string $recipientId,
        public readonly string $recipientType,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null
    ) {}
}
