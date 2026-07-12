<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Domain\Enums;

enum SubscriptionStatus: string
{
    case PENDING_ACTIVATION = 'pending_activation';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
}
