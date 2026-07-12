<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\UseCases;

use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Domain\NotificationType;
use WorkEddy\Modules\Notification\Application\OrganizationNotificationRecipientFactory;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Entities\Subscription;
use WorkEddy\Modules\Subscription\Domain\Events\SubscriptionTierChanged;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;

/**
 * Upgrade/downgrade an active subscription to a different plan tier.
 * Proration/billing adjustment is out of scope here (Phase 2, tracked via
 * the `subscription.plan_changed` event which Billing can subscribe to for
 * proration invoicing once that contract exists).
 */
final class ChangeSubscriptionPlan
{
    public function __construct(
        private readonly ISubscriptionRepository $repository,
        private readonly ISubscriptionPlanRepository $plans,
        private readonly IClock $clock,
        private readonly EventPublisherInterface $events,
        private readonly ?IAuditService $audit = null,
        private readonly ?NotificationServiceInterface $notifications = null,
        private readonly ?OrganizationNotificationRecipientFactory $recipients = null,
    ) {}

    public function execute(string $uuid, string $newPlanCode, ?string $actorId = null): Subscription
    {
        $subscription = $this->repository->findSubscriptionByUuid($uuid);
        if ($subscription === null) {
            throw new NotFoundException('Subscription not found.');
        }

        $newPlan = $this->plans->findByCode($newPlanCode);
        if ($newPlan === null || !$newPlan->isActive) {
            throw new ValidationException(['plan_code' => 'Selected plan is not available.']);
        }

        if ($newPlan->code === $subscription->planCode) {
            return $subscription;
        }

        $oldPlanCode = $subscription->planCode;
        $now = $this->clock->now();

        $updated = $this->repository->changePlan($uuid, $newPlan->code, $newPlan->name, $now);

        $this->audit?->record(
            action: 'subscription.plan_changed',
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
                type: new NotificationType('subscription_plan_changed'),
                recipient: $recipient,
                data: [
                    'old_plan_code' => $oldPlanCode,
                    'new_plan_name' => $updated->planName,
                    'new_plan_code' => $updated->planCode,
                ],
                metadata: ['module' => 'Subscription', 'subscription_uuid' => $updated->uuid],
            ));
        }

        $event = new SubscriptionTierChanged(
            subscriptionUuid: $updated->uuid,
            organizationId: $updated->organizationId,
            organizationUuid: $updated->organizationUuid,
            oldPlanCode: $oldPlanCode,
            newPlanCode: $updated->planCode,
            effectiveDate: $now,
        );

        $this->events->publish(
            SubscriptionTierChanged::NAME,
            $event->toPayload(),
            idempotencyKey: sprintf('subscription.plan_changed:%s:%d', $updated->uuid, $now->getTimestamp()),
        );

        return $updated;
    }
}
