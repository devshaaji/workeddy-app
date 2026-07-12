<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

final class NotificationDispatchResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $notificationUuid = null
    ) {}
}
