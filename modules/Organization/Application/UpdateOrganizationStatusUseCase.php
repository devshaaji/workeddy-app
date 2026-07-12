<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Organization;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;

/**
 * Changes an Organization's lifecycle status (active / suspended /
 * deleted). `deleted` is a soft-delete \u2014 the row is never physically
 * removed (see IOrganizationRepository::softDelete); once soft-deleted, an
 * organization no longer resolves via findById/findByUuid/findBySlug, so
 * it is a terminal state (any further transition attempt on it fails as
 * "not found" rather than needing an explicit state-machine guard).
 *
 * Publishes `organization.status_changed` so dependent modules can react
 * \u2014 in particular Subscription's SuspendSubscriptionOnOrganizationSuspended
 * listener, which suspends/reactivates/cancels the organization's
 * subscription accordingly. See docs/subscription-rework.md \u00a76.2.
 */
final class UpdateOrganizationStatusUseCase
{
    private const VALID_STATUSES = ['active', 'suspended', 'deleted'];

    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
        private readonly EventPublisherInterface $events,
    ) {}

    /**
     * @return array{id: string, name: string, status: string}
     */
    public function execute(string $organizationUuid, string $newStatus, UserContext $actor, ?string $reason = null): array
    {
        $this->permissions->requirePrivilege($actor, OrganizationPermissions::MANAGE);

        if (!in_array($newStatus, self::VALID_STATUSES, true)) {
            throw new ValidationException(['status' => 'Status must be one of: ' . implode(', ', self::VALID_STATUSES) . '.']);
        }

        $organization = $this->organizations->findByUuid($organizationUuid);
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        $oldStatus = $organization->getStatus();
        if ($oldStatus === $newStatus) {
            return ['id' => $organization->getUuid(), 'name' => $organization->getName(), 'status' => $oldStatus];
        }

        $this->tx->transactional(function () use ($organization, $newStatus, $actor, $reason): void {
            if ($newStatus === 'deleted') {
                $this->organizations->softDelete($organization->getUuid());
            } else {
                $this->organizations->update(new Organization(
                    id: $organization->getId(),
                    uuid: $organization->getUuid(),
                    name: $organization->getName(),
                    slug: $organization->getSlug(),
                    status: $newStatus,
                    contactEmail: $organization->getContactEmail(),
                    phone: $organization->getPhone(),
                ));
            }

            $this->audit->record(
                action: 'organization.status_changed',
                entityType: 'Organization',
                entityId: $organization->getUuid(),
                beforeState: ['status' => $organization->getStatus()],
                afterState: ['status' => $newStatus, 'reason' => $reason],
                actorId: (string) $actor->userId,
                actorType: 'User',
            );
        });

        $this->events->publish(
            'organization.status_changed',
            [
                'organization_id' => $organization->getId(),
                'organization_uuid' => $organization->getUuid(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ],
            idempotencyKey: sprintf('organization.status_changed:%s:%s', $organization->getUuid(), $newStatus),
        );

        return ['id' => $organization->getUuid(), 'name' => $organization->getName(), 'status' => $newStatus];
    }
}
