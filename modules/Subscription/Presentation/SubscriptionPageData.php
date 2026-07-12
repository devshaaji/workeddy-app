<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Presentation;

use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Settings\SubscriptionSettings;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class SubscriptionPageData
{
    public function __construct(
        private readonly ISubscriptionRepository $repository,
        private readonly ISubscriptionPlanRepository $plans,
        private readonly SubscriptionSettings $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function index(UserContext $ctx): array
    {
        return [
            'plans' => array_map(static fn($plan): array => $plan->toArray(), $this->plans->listAll()),
            'subscriptions' => array_map(static fn($subscription): array => $subscription->toArray(), $this->repository->listSubscriptions()),
            'defaults' => [
                'default_billing_cycle' => $this->settings->defaultBillingCycle(),
                'default_currency' => $this->settings->defaultCurrency(),
                'trial_days' => $this->settings->trialDays(),
                'grace_period_days' => $this->settings->gracePeriodDays(),
                'auto_suspend_on_expiry' => $this->settings->autoSuspendOnExpiry(),
                'allow_self_service_upgrade' => $this->settings->allowSelfServiceUpgrade(),
            ],
            'user' => (string) $ctx->userId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(string $uuid): array
    {
        $subscription = $this->repository->findSubscriptionByUuid($uuid);
        if ($subscription === null) {
            throw new NotFoundException('Subscription not found.');
        }

        $plan = $this->plans->findByCode($subscription->planCode);

        return [
            'subscription' => $subscription->toArray(),
            'plan' => $plan?->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [
            'plans' => array_map(static fn($plan): array => $plan->toArray(), $this->plans->listAll()),
            'defaults' => [
                'default_billing_cycle' => $this->settings->defaultBillingCycle(),
                'default_currency' => $this->settings->defaultCurrency(),
                'trial_days' => $this->settings->trialDays(),
                'grace_period_days' => $this->settings->gracePeriodDays(),
                'auto_suspend_on_expiry' => $this->settings->autoSuspendOnExpiry(),
                'allow_self_service_upgrade' => $this->settings->allowSelfServiceUpgrade(),
            ],
        ];
    }
}
