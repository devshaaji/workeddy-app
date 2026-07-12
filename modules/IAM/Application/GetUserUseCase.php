<?php

/**
 * GetUserUseCase — retrieve a user's full profile DTO.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Application\DTOs\UserDTO;
use WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class GetUserUseCase
{
    public function __construct(
        private readonly IUserRepository       $userRepo,
        private readonly IOrganizationMembershipRepository $membershipRepo,
        private readonly IRoleRepository        $roleRepo,
        private readonly IPermissionRepository  $permissionRepo,
        private readonly IPermissionService     $permissionService,
    ) {}

    public function execute(int $userId, UserContext $ctx): UserDTO
    {
        $this->permissionService->requirePrivilege($ctx, IAMPermissions::USER_VIEW);

        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            throw new NotFoundException('User', $userId);
        }
        $membership = null;
        if ($ctx->organizationUuid !== null && $ctx->organizationUuid !== '') {
            $membership = $this->membershipRepo->findByUserAndOrganizationUuid($user->getId(), $ctx->organizationUuid);
            if ($membership === null) {
                throw new ForbiddenException('User is outside the active organization scope.');
            }
        }

        $roleId = $membership?->getRoleId() ?? $user->getMembershipRoleId() ?? (int) $user->getRoleId();
        $roleSlug = $membership?->getRoleSlug() ?? $user->getMembershipRoleSlug() ?? $user->getRoleSlug();
        $role = $this->roleRepo->findById($roleId);
        $permissions = $this->permissionRepo->resolveEffectivePermissions(
            $user->getId(),
            $roleId,
        );

        return new UserDTO(
            id: $user->getUuid(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            roleSlug: $roleSlug,
            roleName: $role?->getName() ?? $roleSlug,
            status: $user->getStatus()->value,
            phone: $user->getPhone(),
            permissions: $permissions,
            lastLoginAt: $user->getLastLoginAt(),
            createdAt: $user->getCreatedAt() ?? '',
            organizationId: $membership?->getOrganizationUuid() ?? $user->getOrganizationUuid(),
            organizationName: $membership?->getOrganizationName() ?? $user->getOrganizationName(),
            membershipId: $membership?->getUuid() ?? $user->getMembershipUuid(),
            worksiteId: $membership?->getWorksiteUuid() ?? $user->getWorksiteUuid(),
            departmentId: $membership?->getDepartmentUuid() ?? $user->getDepartmentUuid(),
            jobRoleId: $membership?->getJobRoleUuid() ?? $user->getJobRoleUuid(),
            authzVersion: $user->getAuthzVersion(),
        );
    }
}
