<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Presentation;

use WorkEddy\Modules\IAM\Application\DTOs\UserDTO;
use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Platform\Session\UserContext;

final class UserViewFactory
{
    public function __construct(private readonly IAMUserActionPolicy $userActionPolicy)
    {
    }

    public function listItem(User $user, UserContext $ctx): array
    {
        return array_merge($this->baseView(
            id: $user->getId(),
            uuid: $user->getUuid(),
            email: $user->getEmail(),
            profileFullName: $user->getFullName(),
            profilePhone: $user->getPhone(),
            membershipUuid: $user->getMembershipUuid(),
            organizationUuid: $user->getOrganizationUuid(),
            organizationName: $user->getOrganizationName(),
            roleSlug: $user->getMembershipRoleSlug() ?? $user->getRoleSlug(),
            roleName: null,
            status: $user->getStatus()->value,
            lastLoginAt: $user->getLastLoginAt(),
            createdAt: $user->getCreatedAt(),
            worksiteUuid: $user->getWorksiteUuid(),
            departmentUuid: $user->getDepartmentUuid(),
            jobRoleUuid: $user->getJobRoleUuid(),
        ), [
            'actions' => $this->userActionPolicy->tableActions($ctx, $user),
        ]);
    }

    public function detail(UserDTO $dto): array
    {
        return array_merge($this->baseView(
            id: null,
            uuid: $dto->id,
            email: $dto->email,
            profileFullName: $dto->fullName,
            profilePhone: $dto->phone,
            membershipUuid: $dto->membershipId,
            organizationUuid: $dto->organizationId,
            organizationName: $dto->organizationName,
            roleSlug: $dto->roleSlug,
            roleName: $dto->roleName,
            status: $dto->status,
            lastLoginAt: $dto->lastLoginAt,
            createdAt: $dto->createdAt,
            worksiteUuid: $dto->worksiteId,
            departmentUuid: $dto->departmentId,
            jobRoleUuid: $dto->jobRoleId,
        ), [
            'effectivePermissions' => $dto->permissions,
            'authzVersion' => $dto->authzVersion,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseView(
        int|string|null $id,
        string $uuid,
        string $email,
        string $profileFullName,
        ?string $profilePhone,
        ?string $membershipUuid,
        ?string $organizationUuid,
        ?string $organizationName,
        string $roleSlug,
        ?string $roleName,
        string $status,
        ?string $lastLoginAt,
        ?string $createdAt,
        ?string $worksiteUuid,
        ?string $departmentUuid,
        ?string $jobRoleUuid,
    ): array {
        $membership = [
            'id' => $membershipUuid,
            'organizationUuid' => $organizationUuid,
            'organizationName' => $organizationName,
            'roleSlug' => $roleSlug,
            'worksiteUuid' => $worksiteUuid,
            'departmentUuid' => $departmentUuid,
            'jobRoleUuid' => $jobRoleUuid,
        ];
        if ($roleName !== null) {
            $membership['roleName'] = $roleName;
        }

        return [
            'id' => $id,
            'uuid' => $uuid,
            'email' => $email,
            'profile' => [
                'fullName' => $profileFullName,
                'phone' => $profilePhone,
                'status' => $status,
                'lastLoginAt' => $lastLoginAt,
                'createdAt' => $createdAt,
            ],
            'membership' => $membership,
        ];
    }
}
