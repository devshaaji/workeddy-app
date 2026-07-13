<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

enum NotificationFallbackMode: string
{
    case NEVER = 'never';
    case ON_FAILURE = 'on_failure';
    case ON_PERMANENT_FAILURE = 'on_permanent_failure';
    case ON_UNAVAILABLE = 'on_unavailable';
}
