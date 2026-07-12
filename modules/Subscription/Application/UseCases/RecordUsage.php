<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\UseCases;

use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRecorder;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRepository;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionUsage;
use WorkEddy\Modules\Subscription\Domain\Events\SubscriptionLimitExceeded;
use WorkEddy\Modules\Subscription\Application\Support\SubscriptionMetricCatalog;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;

/**
 * Increments a usage counter for a subscription's current period. Other
 * modules (Assessment, Storage, AI Scoring, Organization, ...) call this in
 * response to their own domain events (assessment.completed,
 * video.uploaded, ai.scoring.completed, organization.member_added,
 * worksite.created) rather than writing to Subscription's tables directly.
 */
final class RecordUsage implements ISubscriptionUsageRecorder
{
    public function __construct(
        private readonly ISubscriptionRepository $subscriptions,
        private readonly ISubscriptionPlanRepository $plans,
        private readonly ISubscriptionUsageRepository $usage,
        private readonly IClock $clock,
        private readonly EventPublisherInterface $events,
        private readonly SubscriptionMetricCatalog $metrics,
    ) {}

    public function forOrganization(int $organizationId, string $metric, int $increment = 1): SubscriptionUsage
    {
        $subscription = $this->subscriptions->findActiveByOrganizationId($organizationId);
        if ($subscription === null) {
            throw new NotFoundException('Organization has no active subscription.');
        }

        $now = $this->clock->now();
        $usageMetric = $this->metrics->usageMetric($metric);
        $usage = $this->usage->recordUsage($subscription->uuid, $usageMetric, $increment, $now);

        $plan = $this->plans->findByCode($subscription->planCode);
        $limit = $plan !== null ? $this->metrics->resolveLimit($plan, $metric) : null;

        if ($plan !== null && $limit !== null && $usage->getUsage($usageMetric) >= $limit) {
            $event = new SubscriptionLimitExceeded(
                subscriptionUuid: $subscription->uuid,
                organizationId: $subscription->organizationId,
                organizationUuid: $subscription->organizationUuid,
                metric: $usageMetric,
                limit: $limit,
                used: $usage->getUsage($usageMetric),
            );

            $this->events->publish(
                SubscriptionLimitExceeded::NAME,
                $event->toPayload(),
                idempotencyKey: sprintf(
                    'subscription.limit_exceeded:%s:%s:%s',
                    $subscription->uuid,
                    $usageMetric,
                    $usage->periodStart->format('Y-m-d'),
                ),
            );
        }

        return $usage;
    }
}
