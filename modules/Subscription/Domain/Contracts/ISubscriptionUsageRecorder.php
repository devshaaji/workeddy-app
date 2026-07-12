<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Domain\Contracts;

use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionUsage;

interface ISubscriptionUsageRecorder
{
    public function forOrganization(int $organizationId, string $metric, int $increment = 1): SubscriptionUsage;
}
