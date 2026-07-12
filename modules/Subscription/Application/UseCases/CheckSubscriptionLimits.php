<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\UseCases;

use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionLimitGuard;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRepository;
use WorkEddy\Modules\Subscription\Domain\ValueObjects\SubscriptionLimits;
use WorkEddy\Modules\Subscription\Application\Support\SubscriptionMetricCatalog;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Exceptions\NotFoundException;

/**
 * Validation service other modules call before allowing an action gated by
 * a plan limit (e.g. "can this org create another worksite?"). Returns a
 * SubscriptionLimits snapshot; callers decide what to do with it (block
 * the action, show an upgrade nudge, etc).
 */
final class CheckSubscriptionLimits implements ISubscriptionLimitGuard
{
    public function __construct(
        private readonly ISubscriptionRepository $subscriptions,
        private readonly ISubscriptionPlanRepository $plans,
        private readonly ISubscriptionUsageRepository $usage,
        private readonly IClock $clock,
        private readonly SubscriptionMetricCatalog $metrics,
    ) {}

    public function forOrganization(int $organizationId, string $metric): SubscriptionLimits
    {
        $subscription = $this->subscriptions->findActiveByOrganizationId($organizationId);
        if ($subscription === null) {
            throw new NotFoundException('Organization has no active subscription.');
        }

        $plan = $this->plans->findByCode($subscription->planCode);
        if ($plan === null) {
            throw new NotFoundException('Subscription plan not found.');
        }

        $currentUsage = $this->usage->getCurrentPeriodUsage($subscription->uuid, $this->clock->now());

        $usageMetric = $this->metrics->usageMetric($metric);

        return SubscriptionLimits::fromValues(
            metric: $metric,
            limit: $this->metrics->resolveLimit($plan, $metric),
            used: $currentUsage?->getUsage($usageMetric) ?? 0,
        );
    }

    public function wouldExceed(int $organizationId, string $metric, int $increment = 1): bool
    {
        return $this->forOrganization($organizationId, $metric)->wouldExceed($increment);
    }
}
