<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application\Processing;

use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;

final class SubscriptionAssessmentVideoProcessingProfileResolver
{
    public function __construct(
        private readonly ISubscriptionRepository $subscriptions,
        private readonly ISubscriptionPlanRepository $plans,
        private readonly AssessmentVideoProcessingProfileResolver $fallback = new AssessmentVideoProcessingProfileResolver(),
    ) {}

    public function resolveForOrganization(?int $organizationId, ?string $fallbackPlanCode = null): AssessmentVideoProcessingProfile
    {
        if ($organizationId === null) {
            return $this->fallback->resolve($fallbackPlanCode);
        }

        $subscription = $this->subscriptions->findActiveByOrganizationId($organizationId);
        if ($subscription === null) {
            return $this->fallback->resolve($fallbackPlanCode);
        }

        $plan = $this->plans->findByCode($subscription->planCode);
        if ($plan === null || !$plan->isActive) {
            return $this->fallback->resolve($subscription->planCode);
        }

        $tier = (string) ($plan->features['video_processing_tier'] ?? $subscription->planCode);

        return $this->fallback->resolve($tier, $plan->features);
    }
}
