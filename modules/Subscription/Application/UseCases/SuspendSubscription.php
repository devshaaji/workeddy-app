<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\UseCases;

use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Domain\NotificationType;
use WorkEddy\Modules\Notification\Application\OrganizationNotificationRecipientFactory;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Entities\Subscription;
use WorkEddy\Modules\Subscription\Domain\Enums\SubscriptionStatus;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class SuspendSubscription
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

        $updated = $this->repository->updateSubscription($subscription, [
            'status' => SubscriptionStatus::SUSPENDED,
            'suspended_at' => $now,
            'suspended_reason' => $reason,
            'updated_at' => $now,
        ]);

        $this->audit?->record(
            action: 'subscription.suspended',
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
                type: new NotificationType('subscription_suspended'),
                recipient: $recipient,
                data: [
                    'plan_name' => $updated->planName,
                    'reason' => $reason ?? 'Subscription suspended',
                ],
                metadata: ['module' => 'Subscription', 'subscription_uuid' => $updated->uuid],
            ));
        }

        $this->events->publish(
            'subscription.suspended',
            [
                'subscription_uuid' => $updated->uuid,
                'organization_id' => $updated->organizationId,
                'organization_uuid' => $updated->organizationUuid,
                'reason' => $reason,
            ],
            idempotencyKey: sprintf('subscription.suspended:%s:%d', $updated->uuid, $now->getTimestamp()),
        );

        return $updated;
    }
}
