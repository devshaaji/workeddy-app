<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Modules\IAM\Application\Services\ModuleUserProvisionerInterface;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Subscription\Application\Support\SubscriptionMetricCatalog;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionLimitGuard;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRecorder;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class InviteOrganizationMemberUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly ModuleUserProvisionerInterface $provisioner,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
        private readonly ISubscriptionLimitGuard $limits,
        private readonly ISubscriptionUsageRecorder $usage,
    ) {}

    /**
     * @return array{userId: string, organizationId: string, roleSlug: string, email: string, status: string}
     */
    public function execute(
        string $organizationUuid,
        string $email,
        string $fullName,
        ?string $phone,
        string $roleSlug,
        UserContext $actor,
    ): array {
        $this->permissions->requirePrivilege($actor, OrganizationPermissions::MEMBERS_MANAGE);

        $organizationUuid = UuidSupport::requireValid($organizationUuid, 'organizationId');
        $organization = $this->organizations->findByUuid($organizationUuid);
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }
        if ($this->limits->wouldExceed((int) $organization->getId(), SubscriptionMetricCatalog::MAX_USERS)) {
            throw new ValidationException(['member' => 'Plan member limit reached. Upgrade to invite more users.']);
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['email' => 'A valid email is required.']);
        }
        if (trim($fullName) === '') {
            throw new ValidationException(['fullName' => 'Full name is required.']);
        }
        if (trim($roleSlug) === '') {
            throw new ValidationException(['roleSlug' => 'Role is required.']);
        }

        $user = $this->provisioner->provisionInvitedUser(
            sourceModule: 'organization',
            sourceType: 'membership',
            sourceId: $organization->getUuid() . ':' . strtolower(trim($email)),
            email: strtolower(trim($email)),
            fullName: trim($fullName),
            phone: $phone !== null && trim($phone) !== '' ? trim($phone) : null,
            roleSlug: trim($roleSlug),
            actorId: (string) $actor->userId,
            metadata: ['organization_uuid' => $organization->getUuid()],
            requiredRoleScope: 'customer',
            organizationUuid: $organization->getUuid(),
        );

        $this->audit->record(
            action: 'organization.member.invited',
            entityType: 'Organization',
            entityId: $organization->getUuid(),
            afterState: [
                'organizationUuid' => $organization->getUuid(),
                'invitedUserUuid' => $user->getUuid(),
                'email' => $user->getEmail(),
                'roleSlug' => $roleSlug,
            ],
            actorId: (string) $actor->userId,
            actorType: 'User',
        );
        $this->usage->forOrganization((int) $organization->getId(), SubscriptionMetricCatalog::MAX_USERS);

        return [
            'userId' => $user->getUuid(),
            'organizationId' => $organization->getUuid(),
            'roleSlug' => trim($roleSlug),
            'email' => $user->getEmail(),
            'status' => $user->getStatus()->value,
        ];
    }
}
