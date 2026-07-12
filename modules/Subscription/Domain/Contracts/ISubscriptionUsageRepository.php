<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Domain\Contracts;

use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionUsage;

interface ISubscriptionUsageRepository
{
    /**
     * Increments (or decrements, with a negative value) a usage metric for
     * the subscription's current period, creating the period row on first
     * use.
     */
    public function recordUsage(string $subscriptionUuid, string $metric, int $increment, \DateTimeImmutable $now): SubscriptionUsage;

    /**
     * Fetches (or lazily creates) the usage row covering "now" for the
     * given subscription.
     */
    public function getCurrentPeriodUsage(string $subscriptionUuid, \DateTimeImmutable $now): SubscriptionUsage;

    public function resetPeriod(string $subscriptionUuid, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): SubscriptionUsage;
}
