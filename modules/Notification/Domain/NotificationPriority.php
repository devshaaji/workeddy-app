<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

enum NotificationPriority: string
{
    case HIGH = 'high';
    case NORMAL = 'normal';
    case LOW = 'low';
}
