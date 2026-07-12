<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Domain\Events;

/**
 * Raised when a subscription's usage for a metric hits or exceeds its plan
 * limit. Published as `subscription.limit_exceeded` via
 * EventPublisherInterface; see CheckSubscriptionLimits / RecordUsage.
 */
final class SubscriptionLimitExceeded
{
    public const NAME = 'subscription.limit_exceeded';

    public function __construct(
        public readonly string $subscriptionUuid,
        public readonly int $organizationId,
        public readonly string $organizationUuid,
        public readonly string $metric,
        public readonly int $limit,
        public readonly int $used,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'subscription_uuid' => $this->subscriptionUuid,
            'organization_id' => $this->organizationId,
            'organization_uuid' => $this->organizationUuid,
            'metric' => $this->metric,
            'limit' => $this->limit,
            'used' => $this->used,
        ];
    }
}
