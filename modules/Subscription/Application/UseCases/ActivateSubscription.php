<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\UseCases;

use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Domain\NotificationType;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Notification\Application\OrganizationNotificationRecipientFactory;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Entities\Subscription;
use WorkEddy\Modules\Subscription\Domain\Enums\SubscriptionStatus;
use WorkEddy\Modules\Subscription\Settings\SubscriptionSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Shared\Exceptions\ConflictException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class ActivateSubscription
{
    public function __construct(
        private readonly ISubscriptionRepository $repository,
        private readonly ISubscriptionPlanRepository $plans,
        private readonly IOrganizationRepository $organizations,
        private readonly SubscriptionSettings $settings,
        private readonly IClock $clock,
        private readonly EventPublisherInterface $events,
        private readonly ?IAuditService $audit = null,
        private readonly ?NotificationServiceInterface $notifications = null,
        private readonly ?OrganizationNotificationRecipientFactory $recipients = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data): Subscription
    {
        foreach (['organization_uuid', 'plan_code'] as $field) {
            if (empty($data[$field])) {
                throw new ValidationException([$field => ucfirst(str_replace('_', ' ', $field)) . ' is required.']);
            }
        }

        $organization = $this->organizations->findByUuid((string) $data['organization_uuid']);
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        $organizationId = $organization->getId();
        if ($organizationId === null) {
            throw new ValidationException(['organization_uuid' => 'Organization is not fully provisioned yet.']);
        }

        $existingActive = $this->repository->findActiveByOrganizationId($organizationId);
        if ($existingActive !== null) {
            throw new ConflictException('Organization already has an active subscription.');
        }

        $plan = $this->plans->findByCode((string) $data['plan_code']);
        if ($plan === null || !$plan->isActive) {
            throw new ValidationException(['plan_code' => 'Selected plan is not available.']);
        }

        $now = $this->clock->now();
        $billingCycle = (string) ($data['billing_cycle'] ?? $plan->billingCycle ?: $this->settings->defaultBillingCycle());

        $startDate = isset($data['start_date']) && $data['start_date'] !== null && $data['start_date'] !== ''
            ? new \DateTimeImmutable((string) $data['start_date'])
            : $now;
        $expiryDate = isset($data['expiry_date']) && $data['expiry_date'] !== null && $data['expiry_date'] !== ''
            ? new \DateTimeImmutable((string) $data['expiry_date'])
            : $startDate->modify($billingCycle === 'annual' ? '+1 year' : '+1 month');

        $subscription = $this->repository->createSubscription([
            'uuid' => (string) ($data['uuid'] ?? UuidSupport::generate()),
            'organization_id' => $organizationId,
            'organization_uuid' => $organization->getUuid(),
            'plan_code' => $plan->code,
            'plan_name' => $plan->name,
            'status' => SubscriptionStatus::ACTIVE,
            'billing_cycle' => $billingCycle,
            'start_date' => $startDate,
            'expiry_date' => $expiryDate,
            'current_period_start' => $startDate,
            'current_period_end' => $expiryDate,
            'activated_at' => $now,
            'auto_renew' => (bool) ($data['auto_renew'] ?? true),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit?->record(
            action: 'subscription.activated',
            entityType: 'Subscription',
            entityId: $subscription->uuid,
            afterState: $subscription->toArray(),
            actorId: isset($data['actor_id']) ? (string) $data['actor_id'] : null,
            metadata: ['module' => 'Subscription'],
        );

        $recipient = $this->recipients?->fromOrganizationId($subscription->organizationUuid);
        if ($recipient !== null && $this->notifications !== null) {
            $this->notifications->send(new NotificationRequest(
                type: new NotificationType('subscription_activated'),
                recipient: $recipient,
                data: [
                    'plan_name' => $subscription->planName,
                    'plan_code' => $subscription->planCode,
                    'expiry_date' => $subscription->expiryDate?->format('Y-m-d'),
                ],
                metadata: ['module' => 'Subscription', 'subscription_uuid' => $subscription->uuid],
            ));
        }

        $this->events->publish(
            'subscription.activated',
            [
                'subscription_uuid' => $subscription->uuid,
                'organization_id' => $subscription->organizationId,
                'organization_uuid' => $subscription->organizationUuid,
                'plan_code' => $subscription->planCode,
                'billing_cycle' => $subscription->billingCycle,
            ],
            idempotencyKey: sprintf('subscription.activated:%s', $subscription->uuid),
        );

        return $subscription;
    }
}
