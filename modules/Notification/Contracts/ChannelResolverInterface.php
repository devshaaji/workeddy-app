<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Contracts;

use WorkEddy\Modules\Notification\Domain\NotificationType;

interface ChannelResolverInterface
{
    /**
     * Returns an array of channels in the order they should be attempted.
     *
     * @return \WorkEddy\Modules\Notification\Domain\NotificationChannel[]
     */
    public function resolve(NotificationType $type): array;
}
