<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\Listeners;

use WorkEddy\Modules\Subscription\Application\UseCases\CancelSubscription;
use WorkEddy\Modules\Subscription\Application\UseCases\ReactivateSubscription;
use WorkEddy\Modules\Subscription\Application\UseCases\SuspendSubscription;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Enums\SubscriptionStatus;
use WorkEddy\Platform\Events\IAsyncEventListener;
use WorkEddy\Platform\Logging\ILoggerFactory;

/**
 * Reacts to `organization.status_changed` (published by
 * UpdateOrganizationStatusUseCase) and keeps the organization's
 * subscription in sync with its lifecycle:
 *
 * - new_status = 'suspended' \u2192 suspend the active subscription, tagged
 *   with a distinguishable `suspended_reason` marker so it can be told
 *   apart from a subscription suspended for its own reasons (e.g.
 *   non-payment via SuspendOverdueSubscriptions).
 * - new_status = 'active' (reactivation) \u2192 only auto-reactivate the
 *   subscription if it is currently suspended *because of* the
 *   organization deactivation \u2014 never touches a subscription suspended
 *   for an unrelated reason (e.g. it should stay suspended for
 *   non-payment even if the org itself is reactivated).
 * - new_status = 'deleted' (soft-delete) \u2192 cancel the subscription
 *   through CancelSubscription (audited, event-published), never a raw
 *   delete \u2014 the subscriptions_organization_fk is RESTRICT specifically
 *   so this is the only path that can make an org-delete succeed while a
 *   subscription still exists.
 */
final class SuspendSubscriptionOnOrganizationSuspended implements IAsyncEventListener
{
    private const DEACTIVATION_REASON_MARKER = 'organization_deactivated';

    public function __construct(
        private readonly ISubscriptionRepository $subscriptions,
        private readonly SuspendSubscription $suspendSubscription,
        private readonly ReactivateSubscription $reactivateSubscription,
        private readonly CancelSubscription $cancelSubscription,
        private readonly ILoggerFactory $loggerFactory,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function __invoke(array $payload): void
    {
        $organizationId = isset($payload['organization_id']) ? (int) $payload['organization_id'] : 0;
        $newStatus = isset($payload['new_status']) ? (string) $payload['new_status'] : '';

        if ($organizationId <= 0 || $newStatus === '') {
            return;
        }

        $logger = $this->loggerFactory->channel('Subscription');
        $subscription = $this->subscriptions->findByOrganizationId($organizationId);
        if ($subscription === null) {
            return;
        }

        try {
            if ($newStatus === 'suspended' && $subscription->status === SubscriptionStatus::ACTIVE) {
                $this->suspendSubscription->execute($subscription->uuid, reason: self::DEACTIVATION_REASON_MARKER, actorId: null);

                return;
            }

            if ($newStatus === 'active'
                && $subscription->status === SubscriptionStatus::SUSPENDED
                && $subscription->suspendedReason === self::DEACTIVATION_REASON_MARKER
            ) {
                $this->reactivateSubscription->execute($subscription->uuid, actorId: null);

                return;
            }

            if ($newStatus === 'deleted'
                && in_array($subscription->status, [SubscriptionStatus::ACTIVE, SubscriptionStatus::SUSPENDED, SubscriptionStatus::PENDING_ACTIVATION], true)
            ) {
                $this->cancelSubscription->execute($subscription->uuid, reason: 'Organization deleted.', actorId: null);
            }
        } catch (\Throwable $exception) {
            $logger->error('Failed to sync subscription state with organization status change.', [
                'organization_id' => $organizationId,
                'subscription_uuid' => $subscription->uuid,
                'new_status' => $newStatus,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
