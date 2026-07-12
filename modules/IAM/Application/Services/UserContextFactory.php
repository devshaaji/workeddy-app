<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Modules\IAM\Domain\OrganizationMembership;
use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Platform\Session\UserContext;

final class UserContextFactory
{
    public function __construct(private readonly IPermissionRepository $permissionRepository)
    {
    }

    public function fromUser(User $user, ?string $loginAt = null): UserContext
    {
        $roleId = $user->getMembershipRoleId() ?? (int) $user->getRoleId();
        $roleSlug = $user->getMembershipRoleSlug() ?? $user->getRoleSlug();
        $permissions = $this->permissionRepository->resolveEffectivePermissions($user->getId(), $roleId);

        return new UserContext(
            tenantId: $user->getOrganizationUuid() ?? 'platform',
            userId: $user->getId(),
            roleId: $roleId,
            organizationId: $user->getOrganizationId(),
            organizationUuid: $user->getOrganizationUuid(),
            membershipId: $user->getMembershipId(),
            membershipUuid: $user->getMembershipUuid(),
            platformRoleId: (int) $user->getRoleId(),
            platformRoleType: $user->getRoleSlug(),
            membershipRoleId: $user->getMembershipRoleId(),
            membershipRoleType: $user->getMembershipRoleSlug(),
            roleType: $roleSlug,
            privileges: $permissions,
            loginAt: $loginAt ?? (new \DateTimeImmutable())->format('c'),
            authzVersion: $user->getAuthzVersion(),
        );
    }

    public function fromMembership(User $user, OrganizationMembership $membership, ?string $loginAt = null): UserContext
    {
        $permissions = $this->permissionRepository->resolveEffectivePermissions($user->getId(), $membership->getRoleId());

        return new UserContext(
            tenantId: $membership->getOrganizationUuid() ?? 'platform',
            userId: $user->getId(),
            roleId: $membership->getRoleId(),
            organizationId: $membership->getOrganizationId(),
            organizationUuid: $membership->getOrganizationUuid(),
            membershipId: $membership->getId(),
            membershipUuid: $membership->getUuid(),
            platformRoleId: (int) $user->getRoleId(),
            platformRoleType: $user->getRoleSlug(),
            membershipRoleId: $membership->getRoleId(),
            membershipRoleType: $membership->getRoleSlug(),
            roleType: $membership->getRoleSlug(),
            privileges: $permissions,
            loginAt: $loginAt ?? (new \DateTimeImmutable())->format('c'),
            authzVersion: $user->getAuthzVersion(),
        );
    }

    public function fromPlatformRole(User $user, ?string $loginAt = null): UserContext
    {
        $permissions = $this->permissionRepository->resolveEffectivePermissions($user->getId(), $user->getRoleId());

        return new UserContext(
            tenantId: 'platform',
            userId: $user->getId(),
            roleId: (int) $user->getRoleId(),
            organizationId: null,
            organizationUuid: null,
            membershipId: null,
            membershipUuid: null,
            platformRoleId: (int) $user->getRoleId(),
            platformRoleType: $user->getRoleSlug(),
            membershipRoleId: null,
            membershipRoleType: null,
            roleType: $user->getRoleSlug(),
            privileges: $permissions,
            loginAt: $loginAt ?? (new \DateTimeImmutable())->format('c'),
            authzVersion: $user->getAuthzVersion(),
        );
    }
}
