<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\UseCases;

use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Domain\NotificationType;
use WorkEddy\Modules\Notification\Application\OrganizationNotificationRecipientFactory;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Entities\Subscription;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;

/**
 * Extends an active, auto-renewing subscription's expiry date by one more
 * billing cycle. Intended to be cron-triggered (see
 * cronjobs/ for the daily sweep that finds subscriptions due to renew) but
 * exposed here as a single-subscription use case so it can also be invoked
 * from an admin action or the `invoice.paid` listener.
 */
final class RenewSubscription
{
    public function __construct(
        private readonly ISubscriptionRepository $repository,
        private readonly IClock $clock,
        private readonly EventPublisherInterface $events,
        private readonly ?IAuditService $audit = null,
        private readonly ?NotificationServiceInterface $notifications = null,
        private readonly ?OrganizationNotificationRecipientFactory $recipients = null,
    ) {}

    public function execute(string $uuid, ?string $actorId = null): Subscription
    {
        $subscription = $this->repository->findSubscriptionByUuid($uuid);
        if ($subscription === null) {
            throw new NotFoundException('Subscription not found.');
        }

        if (!$subscription->autoRenew) {
            throw new ValidationException(['auto_renew' => 'Auto-renew is disabled for this subscription.']);
        }

        $now = $this->clock->now();
        // Advance billing: the new period starts where the current one ends
        // (or "now" as a fallback for a subscription that somehow has no
        // period end yet), not from "now" itself — renewing early never
        // shortens the customer's paid-for time.
        $periodStart = $subscription->currentPeriodEnd ?? $subscription->expiryDate ?? $now;
        $periodEnd = $periodStart->modify($subscription->billingCycle === 'annual' ? '+1 year' : '+1 month');

        $updated = $this->repository->updateSubscription($subscription, [
            'expiry_date' => $periodEnd,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'updated_at' => $now,
        ]);

        $this->audit?->record(
            action: 'subscription.renewed',
            entityType: 'Subscription',
            entityId: $subscription->uuid,
            beforeState: $subscription->toArray(),
            afterState: $updated->toArray(),
            actorId: $actorId,
            metadata: ['module' => 'Subscription'],
        );

        $recipient = $this->recipients?->fromOrganizationId($updated->organizationUuid);
        if ($recipient !== null && $this->notifications !== null) {
            $this->notifications->send(new NotificationRequest(
                type: new NotificationType('subscription_renewed'),
                recipient: $recipient,
                data: [
                    'plan_name' => $updated->planName,
                    'period_start' => $periodStart->format('Y-m-d'),
                    'period_end' => $periodEnd->format('Y-m-d'),
                ],
                metadata: ['module' => 'Subscription', 'subscription_uuid' => $updated->uuid],
            ));
        }

        $this->events->publish(
            'subscription.renewed',
            [
                'subscription_uuid' => $updated->uuid,
                'organization_id' => $updated->organizationId,
                'organization_uuid' => $updated->organizationUuid,
                'plan_code' => $updated->planCode,
                'period_start' => $periodStart->format('Y-m-d H:i:s'),
                'period_end' => $periodEnd->format('Y-m-d H:i:s'),
            ],
            idempotencyKey: sprintf('subscription.renewed:%s:%s', $updated->uuid, $periodEnd->format('Y-m-d')),
        );

        return $updated;
    }
}
