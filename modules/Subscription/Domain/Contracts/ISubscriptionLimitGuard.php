<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Domain\Contracts;

use WorkEddy\Modules\Subscription\Domain\ValueObjects\SubscriptionLimits;

interface ISubscriptionLimitGuard
{
    public function forOrganization(int $organizationId, string $metric): SubscriptionLimits;

    public function wouldExceed(int $organizationId, string $metric, int $increment = 1): bool;
}
