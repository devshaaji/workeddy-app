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

/**
 * Soft-cancel: keeps the subscription record and its history, records a
 * reason, and turns off auto-renew. Distinct from Expire (which fires when
 * a term simply lapses) and from a hard delete (which never happens).
 */
final class CancelSubscription
{
    public function __construct(
        private readonly ISubscriptionRepository $repository,
        private readonly IClock $clock,
        private readonly EventPublisherInterface $events,
        private readonly ?IAuditService $audit = null,
        private readonly ?NotificationServiceInterface $notifications = null,
        private readonly ?OrganizationNotificationRecipientFactory $recipients = null,
    ) {}

    public function execute(string $uuid, ?string $reason = null, ?string $actorId = null): Subscription
    {
        $subscription = $this->repository->findSubscriptionByUuid($uuid);
        if ($subscription === null) {
            throw new NotFoundException('Subscription not found.');
        }

        $now = $this->clock->now();
        $updated = $this->repository->cancelSubscription($uuid, $now, $reason);

        $this->audit?->record(
            action: 'subscription.cancelled',
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
                type: new NotificationType('subscription_cancelled'),
                recipient: $recipient,
                data: [
                    'plan_name' => $updated->planName,
                    'reason' => $reason ?? 'Subscription cancelled',
                    'expires_at' => $updated->expiryDate?->format('Y-m-d'),
                ],
                metadata: ['module' => 'Subscription', 'subscription_uuid' => $updated->uuid],
            ));
        }

        $this->events->publish(
            'subscription.cancelled',
            [
                'subscription_uuid' => $updated->uuid,
                'organization_id' => $updated->organizationId,
                'organization_uuid' => $updated->organizationUuid,
                'reason' => $reason,
                'expires_at' => $updated->expiryDate?->format('Y-m-d H:i:s'),
            ],
            idempotencyKey: sprintf('subscription.cancelled:%s:%d', $updated->uuid, $now->getTimestamp()),
        );

        return $updated;
    }
}
