<?php

declare(strict_types=1);

namespace WorkEddy\Tests\IAM;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\IAM\Application\GetUserUseCase;
use WorkEddy\Modules\IAM\Presentation\IAMPageData;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\Role;
use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Modules\IAM\Domain\UserStatus;
use WorkEddy\Modules\IAM\Presentation\IAMUserActionPolicy;
use WorkEddy\Modules\IAM\Presentation\UserViewFactory;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\SettingsRegistry;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Exceptions\ForbiddenException;

final class IamTenantUserViewTest extends TestCase
{
    public function test_user_view_factory_exposes_profile_and_membership_context(): void
    {
        $user = new User(
            id: 14,
            uuid: '11111111-1111-4111-8111-111111111111',
            email: 'user@example.test',
            fullName: 'User Example',
            passwordHash: 'hash',
            roleId: 2,
            roleSlug: 'worker',
            status: UserStatus::ACTIVE,
            phone: '+2348000000000',
            lastLoginAt: '2026-07-07 10:00:00',
            authzVersion: 3,
            createdAt: '2026-07-01 09:00:00',
            updatedAt: '2026-07-07 10:00:00',
            organizationId: 9,
            organizationUuid: '22222222-2222-4222-8222-222222222222',
            organizationName: 'Acme Safety Group',
            membershipId: 21,
            membershipUuid: '33333333-3333-4333-8333-333333333333',
            worksiteId: 31,
            worksiteUuid: '44444444-4444-4444-8444-444444444444',
            departmentId: 41,
            departmentUuid: '55555555-5555-4555-8555-555555555555',
            jobRoleId: 51,
            jobRoleUuid: '66666666-6666-4666-8666-666666666666',
        );

        $view = (new UserViewFactory(new IAMUserActionPolicy()))->listItem(
            $user,
            new UserContext(
                userId: 1,
                roleType: 'system',
                privileges: ['iam.user.view', 'iam.user.update'],
            ),
        );

        self::assertSame('User Example', $view['profile']['fullName']);
        self::assertSame('+2348000000000', $view['profile']['phone']);
        self::assertSame('active', $view['profile']['status']);
        self::assertSame('2026-07-01 09:00:00', $view['profile']['createdAt']);
        self::assertSame('2026-07-07 10:00:00', $view['profile']['lastLoginAt']);
        self::assertSame('33333333-3333-4333-8333-333333333333', $view['membership']['id']);
        self::assertSame('22222222-2222-4222-8222-222222222222', $view['membership']['organizationUuid']);
        self::assertSame('Acme Safety Group', $view['membership']['organizationName']);
        self::assertSame('worker', $view['membership']['roleSlug']);
        self::assertSame('55555555-5555-4555-8555-555555555555', $view['membership']['departmentUuid']);
        self::assertSame(14, $view['id']);
        self::assertArrayNotHasKey('fullName', $view);
        self::assertArrayNotHasKey('phone', $view);
        self::assertArrayNotHasKey('status', $view);
        self::assertArrayNotHasKey('roleSlug', $view);
        self::assertArrayNotHasKey('roleName', $view['membership']);
    }

    public function test_get_user_use_case_includes_effective_permissions_and_scope_context(): void
    {
        $user = new User(
            id: 14,
            uuid: '11111111-1111-4111-8111-111111111111',
            email: 'user@example.test',
            fullName: 'User Example',
            passwordHash: 'hash',
            roleId: 2,
            roleSlug: 'worker',
            status: UserStatus::ACTIVE,
            phone: '+2348000000000',
            lastLoginAt: '2026-07-07 10:00:00',
            authzVersion: 3,
            createdAt: '2026-07-01 09:00:00',
            updatedAt: '2026-07-07 10:00:00',
            organizationId: 9,
            organizationUuid: '22222222-2222-4222-8222-222222222222',
            organizationName: 'Acme Safety Group',
            membershipId: 21,
            membershipUuid: '33333333-3333-4333-8333-333333333333',
            worksiteId: 31,
            worksiteUuid: '44444444-4444-4444-8444-444444444444',
            departmentId: 41,
            departmentUuid: '55555555-5555-4555-8555-555555555555',
            jobRoleId: 51,
            jobRoleUuid: '66666666-6666-4666-8666-666666666666',
        );
        $membership = new \WorkEddy\Modules\IAM\Domain\OrganizationMembership(
            id: 21,
            uuid: '33333333-3333-4333-8333-333333333333',
            userId: 14,
            organizationId: 9,
            organizationUuid: '22222222-2222-4222-8222-222222222222',
            roleId: 2,
            roleSlug: 'worker',
            worksiteId: 31,
            worksiteUuid: '44444444-4444-4444-8444-444444444444',
            departmentId: 41,
            departmentUuid: '55555555-5555-4555-8555-555555555555',
            jobRoleId: 51,
            jobRoleUuid: '66666666-6666-4666-8666-666666666666',
            status: 'active',
            isPrimary: true,
            organizationName: 'Acme Safety Group'
        );
        $useCase = new GetUserUseCase(
            new SingleUserRepository($user),
            new FakeOrganizationMembershipRepository($membership),
            new SingleRoleRepository(new Role(
                id: 2,
                uuid: '77777777-7777-4777-8777-777777777777',
                slug: 'worker',
                name: 'Worker',
                description: null,
                isSystem: false,
                scope: 'customer',
                permissions: ['iam.user.view'],
            )),
            new StaticPermissionRepository(['iam.user.view', 'task.view']),
            new AllowingIamViewPermissionService(),
        );

        $dto = $useCase->execute(
            14,
            new UserContext(
                userId: 1,
                organizationId: 9,
                organizationUuid: '22222222-2222-4222-8222-222222222222',
                roleType: 'system',
                privileges: ['iam.user.view'],
            ),
        );

        self::assertSame('22222222-2222-4222-8222-222222222222', $dto->organizationId);
        self::assertSame('33333333-3333-4333-8333-333333333333', $dto->membershipId);
        self::assertSame(['iam.user.view', 'task.view'], $dto->permissions);
        self::assertSame('55555555-5555-4555-8555-555555555555', $dto->departmentId);
    }

    public function test_get_user_use_case_rejects_cross_organization_access_for_scoped_actor(): void
    {
        $user = new User(
            id: 14,
            uuid: '11111111-1111-4111-8111-111111111111',
            email: 'user@example.test',
            fullName: 'User Example',
            passwordHash: 'hash',
            roleId: 2,
            roleSlug: 'worker',
            status: UserStatus::ACTIVE,
            organizationId: 9,
            organizationUuid: '22222222-2222-4222-8222-222222222222',
        );

        $useCase = new GetUserUseCase(
            new SingleUserRepository($user),
            new FakeOrganizationMembershipRepository(null),
            new SingleRoleRepository(new Role(
                id: 2,
                uuid: '77777777-7777-4777-8777-777777777777',
                slug: 'worker',
                name: 'Worker',
                description: null,
                isSystem: false,
                scope: 'customer',
                permissions: [],
            )),
            new StaticPermissionRepository([]),
            new AllowingIamViewPermissionService(),
        );

        $this->expectException(ForbiddenException::class);

        $useCase->execute(
            14,
            new UserContext(
                userId: 1,
                organizationId: 77,
                organizationUuid: '99999999-9999-4999-8999-999999999999',
                roleType: 'system',
                privileges: ['iam.user.view'],
            ),
        );
    }

    public function test_page_data_returns_tenant_membership_shape_for_profile_and_user(): void
    {
        $user = new User(
            id: 14,
            uuid: '11111111-1111-4111-8111-111111111111',
            email: 'user@example.test',
            fullName: 'User Example',
            passwordHash: 'hash',
            roleId: 2,
            roleSlug: 'worker',
            status: UserStatus::ACTIVE,
            phone: '+2348000000000',
            organizationId: 9,
            organizationUuid: '22222222-2222-4222-8222-222222222222',
            organizationName: 'Acme Safety Group',
            membershipId: 21,
            membershipUuid: '33333333-3333-4333-8333-333333333333',
            worksiteId: 31,
            worksiteUuid: '44444444-4444-4444-8444-444444444444',
            departmentId: 41,
            departmentUuid: '55555555-5555-4555-8555-555555555555',
            jobRoleId: 51,
            jobRoleUuid: '66666666-6666-4666-8666-666666666666',
        );

        $membership = new \WorkEddy\Modules\IAM\Domain\OrganizationMembership(
            id: 21,
            uuid: '33333333-3333-4333-8333-333333333333',
            userId: 14,
            organizationId: 9,
            organizationUuid: '22222222-2222-4222-8222-222222222222',
            roleId: 2,
            roleSlug: 'worker',
            worksiteId: 31,
            worksiteUuid: '44444444-4444-4444-8444-444444444444',
            departmentId: 41,
            departmentUuid: '55555555-5555-4555-8555-555555555555',
            jobRoleId: 51,
            jobRoleUuid: '66666666-6666-4666-8666-666666666666',
            status: 'active',
            isPrimary: true,
            organizationName: 'Acme Safety Group'
        );

        $pageData = new IAMPageData(
            new SingleUserRepository($user),
            new FakeOrganizationMembershipRepository($membership),
            new SingleRoleRepository(new Role(
                id: 2,
                uuid: '77777777-7777-4777-8777-777777777777',
                slug: 'worker',
                name: 'Worker',
                description: null,
                isSystem: false,
                scope: 'customer',
                permissions: [],
            )),
            new StaticPermissionRepository([]),
            SettingsRegistry::fromProviders([]),
            new SettingsService([]),
            new IAMUserActionPolicy(),
        );

        $profile = $pageData->profile(new UserContext(
            userId: 14,
            organizationId: 9,
            organizationUuid: '22222222-2222-4222-8222-222222222222',
            roleType: 'worker',
            privileges: ['iam.user.view'],
        ));
        $userView = $pageData->user(new UserContext(
            userId: 14,
            organizationId: 9,
            organizationUuid: '22222222-2222-4222-8222-222222222222',
            roleType: 'worker',
            privileges: ['iam.user.view'],
        ), '11111111-1111-4111-8111-111111111111');

        self::assertSame('User Example', $profile['profile']['profile']['fullName']);
        self::assertSame('active', $profile['profile']['profile']['status']);
        self::assertSame('22222222-2222-4222-8222-222222222222', $profile['profile']['membership']['organizationUuid']);
        self::assertSame('33333333-3333-4333-8333-333333333333', $profile['profile']['membership']['id']);
        self::assertSame('Acme Safety Group', $userView['user']['membership']['organizationName']);
        self::assertSame('Worker', $userView['user']['membership']['roleName']);
        self::assertSame('active', $userView['user']['profile']['status']);
        self::assertSame('55555555-5555-4555-8555-555555555555', $userView['user']['membership']['departmentUuid']);
        self::assertArrayNotHasKey('fullName', $profile['profile']);
        self::assertArrayNotHasKey('status', $profile['profile']);
        self::assertArrayNotHasKey('roleSlug', $profile['profile']);
    }
}

final class SingleUserRepository implements IUserRepository
{
    public function __construct(private readonly User $user)
    {
    }

    public function create(User $user): int|string
    {
        return 1;
    }

    public function update(User $user): void
    {
    }

    public function findById(int|string $id): ?User
    {
        return (int) $id === (int) $this->user->getId() ? $this->user : null;
    }

    public function findByUuid(string $uuid): ?User
    {
        return $uuid === $this->user->getUuid() ? $this->user : null;
    }

    public function findByEmail(string $email): ?User
    {
        return strtolower($email) === strtolower($this->user->getEmail()) ? $this->user : null;
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return [$this->user];
    }

    public function count(array $filters = []): int
    {
        return 1;
    }

    public function countByRoleIds(array $roleIds): array
    {
        return [];
    }

    public function findByRoleId(int|string $roleId, int $limit = 25): array
    {
        return [];
    }

    public function bumpAuthzVersion(int|string $userId): int
    {
        return $this->user->getAuthzVersion();
    }
}

final class SingleRoleRepository implements IRoleRepository
{
    public function __construct(private readonly Role $role)
    {
    }

    public function findById(int|string $id): ?Role
    {
        return (int) $id === (int) $this->role->getId() ? $this->role : null;
    }

    public function findByUuid(string $uuid): ?Role
    {
        return $uuid === $this->role->getUuid() ? $this->role : null;
    }

    public function findBySlug(string $slug): ?Role
    {
        return $slug === $this->role->getSlug() ? $this->role : null;
    }

    public function findAll(): array
    {
        return [$this->role];
    }

    public function create(Role $role): int|string
    {
        return 1;
    }

    public function update(Role $role): void
    {
    }

    public function getPermissionsForRole(int|string $roleId): array
    {
        return $this->role->getPermissions();
    }

    public function syncPermissions(int|string $roleId, array $permissionIds): void
    {
    }
}

final class StaticPermissionRepository implements IPermissionRepository
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(private readonly array $permissions)
    {
    }

    public function findById(int|string $id): ?\WorkEddy\Modules\IAM\Domain\Permission
    {
        return null;
    }

    public function findByUuid(string $uuid): ?\WorkEddy\Modules\IAM\Domain\Permission
    {
        return null;
    }

    public function findBySlug(string $slug): ?\WorkEddy\Modules\IAM\Domain\Permission
    {
        return null;
    }

    public function findAll(): array
    {
        return [];
    }

    public function findByModule(string $module): array
    {
        return [];
    }

    public function resolveEffectivePermissions(int|string $userId, int|string $roleId): array
    {
        return $this->permissions;
    }

    public function listUserPermissionOverrides(int|string $userId): array
    {
        return [];
    }

    public function replaceUserPermissionOverrides(int|string $userId, array $grantPermissionIds, array $denyPermissionIds, int|string $actorId): void
    {
    }

    public function upsertCatalog(array $definitions): int
    {
        return 0;
    }
}

final class AllowingIamViewPermissionService implements IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        if (!in_array($privilege, $ctx->privileges, true)) {
            throw new \RuntimeException('Missing expected privilege in test context: ' . $privilege);
        }
    }
}

final class FakeOrganizationMembershipRepository implements \WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository
{
    public function __construct(private readonly ?\WorkEddy\Modules\IAM\Domain\OrganizationMembership $membership = null) {}
    public function create(\WorkEddy\Modules\IAM\Domain\OrganizationMembership $membership): int { return 1; }
    public function update(\WorkEddy\Modules\IAM\Domain\OrganizationMembership $membership): void {}
    public function delete(string $uuid): void {}
    public function findByUuid(string $uuid): ?\WorkEddy\Modules\IAM\Domain\OrganizationMembership { return $this->membership; }
    public function findPrimaryByUserId(int|string $userId): ?\WorkEddy\Modules\IAM\Domain\OrganizationMembership { return $this->membership; }
    public function findByUserAndOrganizationUuid(int|string $userId, string $organizationUuid): ?\WorkEddy\Modules\IAM\Domain\OrganizationMembership {
        return $this->membership;
    }
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array {
        return $this->membership ? [$this->membership] : [];
    }
    public function findAllByUserId(int|string $userId): array {
        return $this->membership ? [$this->membership] : [];
    }
}
