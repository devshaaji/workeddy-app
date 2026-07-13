<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

final class NotificationChannelPolicy
{
    /**
     * @param list<NotificationChannel> $channels
     */
    public function __construct(
        public readonly array $channels,
        public readonly NotificationFallbackMode $fallbackMode,
    ) {}
}
