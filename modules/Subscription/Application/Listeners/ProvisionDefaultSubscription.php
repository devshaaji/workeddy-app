<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\Listeners;

use WorkEddy\Modules\Subscription\Application\UseCases\ActivateSubscription;
use WorkEddy\Modules\Subscription\Settings\SubscriptionSettings;
use WorkEddy\Platform\Events\IAsyncEventListener;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Shared\Exceptions\ConflictException;

/**
 * Reacts to `organization.created` by activating a default-tier
 * subscription for the new Organization, when auto-provisioning is turned
 * on (see SubscriptionSettings::autoProvisionOnSignup /
 * ::defaultPlanCode). Off by default so deployments doing
 * sales-assisted/manual onboarding aren't surprised by a subscription
 * appearing on every signup.
 */
final class ProvisionDefaultSubscription implements IAsyncEventListener
{
    public function __construct(
        private readonly ActivateSubscription $activateSubscription,
        private readonly SubscriptionSettings $settings,
        private readonly ILoggerFactory $loggerFactory,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function __invoke(array $payload): void
    {
        if (!$this->settings->autoProvisionOnSignup()) {
            return;
        }

        $planCode = trim($this->settings->defaultPlanCode());
        if ($planCode === '') {
            return;
        }

        $organizationUuid = isset($payload['organization_uuid']) ? (string) $payload['organization_uuid'] : '';
        if ($organizationUuid === '') {
            return;
        }

        $logger = $this->loggerFactory->channel('Subscription');

        try {
            $this->activateSubscription->execute([
                'organization_uuid' => $organizationUuid,
                'plan_code' => $planCode,
            ]);
        } catch (ConflictException) {
            // Organization already has an active subscription (e.g. a
            // redelivered event, or it was provisioned another way).
            // Nothing to do.
        } catch (\Throwable $exception) {
            $logger->error('Failed to auto-provision default subscription for new organization.', [
                'organization_uuid' => $organizationUuid,
                'plan_code' => $planCode,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
