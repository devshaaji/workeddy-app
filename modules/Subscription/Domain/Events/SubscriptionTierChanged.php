<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Domain\Events;

/**
 * Raised when a subscription moves from one plan tier to another
 * (upgrade or downgrade). Published as `subscription.plan_changed` via
 * EventPublisherInterface; see ChangeSubscriptionPlan.
 */
final class SubscriptionTierChanged
{
    public const NAME = 'subscription.plan_changed';

    public function __construct(
        public readonly string $subscriptionUuid,
        public readonly int $organizationId,
        public readonly string $organizationUuid,
        public readonly string $oldPlanCode,
        public readonly string $newPlanCode,
        public readonly \DateTimeImmutable $effectiveDate,
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
            'old_plan_code' => $this->oldPlanCode,
            'new_plan_code' => $this->newPlanCode,
            'effective_date' => $this->effectiveDate->format('Y-m-d H:i:s'),
        ];
    }
}
