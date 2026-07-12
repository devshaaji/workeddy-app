<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Presentation;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\Permission;
use WorkEddy\Modules\IAM\Domain\Role;
use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Modules\IAM\Application\DTOs\UserDTO;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\SettingsRegistry;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class IAMPageData
{
    public function __construct(
        private readonly IUserRepository $users,
        private readonly IOrganizationMembershipRepository $memberships,
        private readonly IRoleRepository $roles,
        private readonly IPermissionRepository $permissions,
        private readonly SettingsRegistry $settingsRegistry,
        private readonly SettingsService $settings,
        private readonly IAMUserActionPolicy $userActions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function common(UserContext $ctx): array
    {
        return [
            'can' => $this->userActions->can($ctx),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(UserContext $ctx): array
    {
        $user = $this->users->findById($ctx->userId);
        if ($user === null) {
            throw new AuthenticationException('Session user no longer exists.');
        }

        return ['profile' => $this->serializeUser($user, includeRoleName: true, organizationUuid: $ctx->organizationUuid)];
    }

    /**
     * @return array<string, mixed>
     */
    public function user(UserContext $ctx, string $userUuid): array
    {
        $userUuid = UuidSupport::requireValid($userUuid);
        $user = $this->users->findByUuid($userUuid);
        if ($user === null) {
            throw new NotFoundException('User ' . $userUuid);
        }

        return [
            'user' => $this->serializeUser($user, includeRoleName: true, organizationUuid: $ctx->organizationUuid),
            'userActions' => $this->userActions->workflowActions($ctx, $user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function role(string $roleUuid): array
    {
        $roleUuid = UuidSupport::requireValid($roleUuid);
        $role = $this->roles->findByUuid($roleUuid);
        if ($role === null) {
            throw new NotFoundException('Role ' . $roleUuid);
        }

        return ['role' => $this->serializeRole($role)];
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $values = $this->settings->getAllForModule('iam');
        $definitions = $this->settingsRegistry->getForModule('iam');

        return ['settings' => array_map(static fn($value): mixed => $value, $values), 'settingDefinitions' => $definitions];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user, bool $includeRoleName = false, ?string $organizationUuid = null): array
    {
        $membership = null;
        if ($organizationUuid !== null && trim($organizationUuid) !== '') {
            $membership = $this->memberships->findByUserAndOrganizationUuid((int) $user->getId(), $organizationUuid);
        }

        $roleId = $membership?->getRoleId() ?? $user->getMembershipRoleId() ?? (int) $user->getRoleId();
        $roleSlug = $membership?->getRoleSlug() ?? $user->getMembershipRoleSlug() ?? $user->getRoleSlug();
        $role = $includeRoleName ? $this->roles->findById($roleId) : null;

        return [
            'uuid' => $user->getUuid(),
            'email' => $user->getEmail(),
            'profile' => [
                'fullName' => $user->getFullName(),
                'phone' => $user->getPhone(),
                'status' => $user->getStatus()->value,
                'lastLoginAt' => $user->getLastLoginAt(),
                'createdAt' => $user->getCreatedAt(),
            ],
            'membership' => [
                'id' => $membership?->getUuid() ?? $user->getMembershipUuid(),
                'organizationUuid' => $membership?->getOrganizationUuid() ?? $user->getOrganizationUuid(),
                'organizationName' => $membership?->getOrganizationName() ?? $user->getOrganizationName(),
                'roleSlug' => $roleSlug,
                'roleName' => $role?->getName() ?? $roleSlug,
                'worksiteUuid' => $membership?->getWorksiteUuid() ?? $user->getWorksiteUuid(),
                'departmentUuid' => $membership?->getDepartmentUuid() ?? $user->getDepartmentUuid(),
                'jobRoleUuid' => $membership?->getJobRoleUuid() ?? $user->getJobRoleUuid(),
                'platformRoleSlug' => $user->getRoleSlug(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRole(Role $role): array
    {
        $permissionMap = $this->permissionMap();
        $roleId = (int) $role->getId();
        $counts = $this->users->countByRoleIds([$roleId]);

        return [
            'id' => $role->getUuid(),
            'slug' => $role->getSlug(),
            'name' => $role->getName(),
            'description' => $role->getDescription(),
            'isSystem' => $role->isSystem(),
            'permissionCount' => count($role->getPermissions()),
            'riskLevel' => $this->deriveRiskLevel($role->getPermissions(), $permissionMap),
            'userCount' => $counts[$roleId] ?? 0,
        ];
    }

    /**
     * @return array<string, Permission>
     */
    private function permissionMap(): array
    {
        $map = [];
        foreach ($this->permissions->findAll() as $permission) {
            $map[$permission->slug] = $permission;
        }

        return $map;
    }

    /**
     * @param string[] $permissionSlugs
     * @param array<string, Permission> $permissionMap
     */
    private function deriveRiskLevel(array $permissionSlugs, array $permissionMap): ?string
    {
        $rank = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $highest = null;
        $highestRank = 0;

        foreach ($permissionSlugs as $slug) {
            $risk = strtolower((string) ($permissionMap[$slug]->riskLevel ?? ''));
            if (($rank[$risk] ?? 0) > $highestRank) {
                $highest = $risk;
                $highestRank = $rank[$risk];
            }
        }

        return $highest;
    }
}
