<?php

declare(strict_types=1);

namespace WorkEddy\Tests\IAM;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Doctrine\DBAL\DriverManager;
use WorkEddy\Modules\IAM\Application\AssignRoleUseCase;
use WorkEddy\Modules\IAM\Application\DTOs\AssignRoleRequest;
use WorkEddy\Modules\IAM\Application\DTOs\LoginRequest;
use WorkEddy\Modules\IAM\Application\DTOs\PublicRegisterRequest;
use WorkEddy\Modules\IAM\Application\DTOs\UpdateUserRequest;
use WorkEddy\Modules\IAM\Application\LoginUseCase;
use WorkEddy\Modules\IAM\Application\PublicRegisterUseCase;
use WorkEddy\Modules\IAM\Application\RequestPasswordResetUseCase;
use WorkEddy\Modules\IAM\Application\ResendOTPUseCase;
use WorkEddy\Modules\IAM\Application\ResetPasswordUseCase;
use WorkEddy\Modules\IAM\Application\UpdateUserUseCase;
use WorkEddy\Modules\IAM\Application\VerifyOTPUseCase;
use WorkEddy\Modules\IAM\Application\Services\IAMNotificationDispatcher;
use WorkEddy\Modules\IAM\Application\Services\ModuleUserProvisioner;
use WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserProfileRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\OrganizationMembership;
use WorkEddy\Modules\IAM\Domain\Role;
use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Modules\IAM\Domain\UserProfile;
use WorkEddy\Modules\IAM\Domain\UserStatus;
use WorkEddy\Modules\IAM\Infrastructure\OTPRepository;
use WorkEddy\Modules\IAM\Authorization\IAMPermissionDefinitionProvider;
use WorkEddy\Modules\IAM\Settings\IAMSettings;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Organization;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Events\InMemoryEventPublisher;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Platform\Settings\SettingsRegistry;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Modules\IAM\Application\Services\AuthenticationThrottleService;
use WorkEddy\Modules\IAM\Application\Services\IAMAuthNotificationDispatcher;
use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Notification\Domain\NotificationDispatchResult;
use WorkEddy\Modules\Notification\Domain\NotificationRequest as NotificationDomainRequest;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Entities\Subscription;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionPlan;
use WorkEddy\Modules\Subscription\Domain\Enums\SubscriptionStatus;
use WorkEddy\Modules\IAM\Application\Services\PlatformRoleResolver;
use WorkEddy\Modules\IAM\Application\Services\UserContextFactory;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use Psr\Log\LoggerInterface;

final class PhaseOneIamBehaviorTest extends TestCase
{
    public function test_assign_role_updates_primary_membership_when_present(): void
    {
        $users = new InMemoryIamUserRepository([
            new User(
                id: 14,
                uuid: '11111111-1111-4111-8111-111111111111',
                email: 'user@example.test',
                fullName: 'User Example',
                passwordHash: 'hash',
                roleId: 1,
                roleSlug: 'worker',
                status: UserStatus::ACTIVE,
            ),
        ]);
        $roles = new InMemoryRoleRepository([
            new Role(id: 8, uuid: '22222222-2222-4222-8222-222222222222', slug: 'safety_manager', name: 'Safety Manager', description: null, isSystem: false, scope: 'customer', permissions: []),
        ]);
        $memberships = new InMemoryMembershipRepository(
            new OrganizationMembership(
                id: 99,
                uuid: '33333333-3333-4333-8333-333333333333',
                userId: 14,
                organizationId: 5,
                organizationUuid: '44444444-4444-4444-8444-444444444444',
                roleId: 1,
                roleSlug: 'worker',
            )
        );
        $notificationService = new RecordingNotificationService();
        $notifications = new IAMNotificationDispatcher(
            $notificationService,
            $users,
            new IAMSettings(new SettingsService([])),
        );

        $useCase = new AssignRoleUseCase(
            $users,
            $roles,
            $memberships,
            new AllowingPermissionService(),
            new PassthroughIamTransactionManager(),
            new RecordingIamAuditService(),
            $notifications,
        );

        $useCase->execute(
            new AssignRoleRequest(userId: 14, roleSlug: 'safety_manager'),
            new UserContext(userId: 1, roleType: 'system', privileges: ['iam.role.assign']),
        );

        self::assertSame('worker', $users->findById(14)?->getRoleSlug());
        self::assertSame('safety_manager', $memberships->membership?->getRoleSlug());
    }

    public function test_update_user_creates_profile_when_missing(): void
    {
        $users = new InMemoryIamUserRepository([
            new User(
                id: 14,
                uuid: '11111111-1111-4111-8111-111111111111',
                email: 'user@example.test',
                fullName: 'Old Name',
                passwordHash: 'hash',
                roleId: 2,
                roleSlug: 'worker',
                status: UserStatus::ACTIVE,
                phone: null,
            ),
        ]);
        $profiles = new InMemoryProfileRepository();
        $roles = new InMemoryRoleRepository([
            new Role(id: 2, uuid: '22222222-2222-4222-8222-222222222222', slug: 'worker', name: 'Worker', description: null, isSystem: false, scope: 'customer', permissions: []),
        ]);

        $useCase = new UpdateUserUseCase(
            $users,
            $profiles,
            $roles,
            new NoopPermissionRepository(),
            new AllowingPermissionService(),
            new PassthroughIamTransactionManager(),
            new RecordingIamAuditService(),
        );

        $dto = $useCase->execute(
            new UpdateUserRequest(userId: 14, fullName: 'New Name', email: 'new@example.test', phone: '+2348000000000'),
            new UserContext(userId: 1, roleType: 'system', privileges: ['iam.user.update']),
        );

        self::assertSame('New Name', $dto->fullName);
        self::assertSame('+2348000000000', $profiles->profile?->getPhone());
        self::assertSame('New Name', $profiles->profile?->getFullName());
    }

    public function test_auth_controller_returns_uuid_for_login_and_register(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE iam_otp_challenges (challenge_id TEXT PRIMARY KEY, user_id INTEGER, purpose TEXT, code_hash TEXT, expires_at TEXT, consumed_at TEXT NULL, created_at TEXT, updated_at TEXT)');

        $users = new InMemoryIamUserRepository([
            new User(
                id: 14,
                uuid: '11111111-1111-4111-8111-111111111111',
                email: 'user@example.test',
                fullName: 'User Example',
                passwordHash: password_hash('secret', PASSWORD_BCRYPT),
                roleId: 2,
                roleSlug: 'worker',
                status: UserStatus::ACTIVE,
                phone: '+2348000000000',
                organizationId: 9,
                organizationUuid: '22222222-2222-4222-8222-222222222222',
                membershipId: 21,
                membershipUuid: '33333333-3333-4333-8333-333333333333',
            ),
        ]);
        $profiles = new InMemoryProfileRepository();
        $roles = new InMemoryRoleRepository([
            new Role(id: 2, uuid: '22222222-2222-4222-8222-222222222222', slug: 'worker', name: 'Worker', description: null, isSystem: false, scope: 'customer', permissions: ['iam.user.view']),
            new Role(id: 3, uuid: '33333333-3333-4333-8333-333333333333', slug: 'admin', name: 'Admin', description: null, isSystem: true, scope: 'staff', permissions: ['iam.user.view']),
        ]);
        $permissions = new NoopPermissionRepository();
        $settings = new IAMSettings(new SettingsService([
            'iam.auth_otp_enabled' => false,
            'iam.password_algorithm' => 'bcrypt',
            'iam.argon2_memory_cost' => 65536,
            'iam.argon2_time_cost' => 4,
            'iam.argon2_threads' => 1,
            'iam.max_login_attempts' => 5,
            'iam.lockout_duration_minutes' => 15,
            'iam.min_password_length' => 8,
            'iam.default_user_status_active' => true,
            'iam.public_registration_allowed_roles' => ['worker'],
        ]));
        $throttle = new AuthenticationThrottleService(new InMemoryCacheService(), $settings);
        $otpRepository = new OTPRepository($connection, new ConfigLoader(), new FixedClock('2026-07-07 00:00:00'));
        $notificationService = new RecordingNotificationService();
        $iamNotifications = new IAMNotificationDispatcher($notificationService, $users, $settings);
        $authNotifications = new IAMAuthNotificationDispatcher($notificationService, new ConfigLoader());
        $session = new InMemorySessionService();
        $audit = new RecordingIamAuditService();
        $clock = new FixedClock('2026-07-07 00:00:00');
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository(null);
        $subscriptionRepo = new InMemorySubscriptionRepository([
            new SubscriptionPlan(
                id: 1,
                code: 'free',
                name: 'Free Trial',
                description: 'Free Trial Plan',
                billingCycle: 'monthly',
                price: 0.0,
                currency: 'USD',
                features: [],
                isActive: true,
                displayOrder: 1,
                createdAt: new \DateTimeImmutable('2026-07-07 00:00:00'),
                updatedAt: new \DateTimeImmutable('2026-07-07 00:00:00'),
            ),
        ]);
        $loginUseCase = new LoginUseCase(
            $users,
            $permissions,
            $otpRepository,
            $throttle,
            $session,
            $audit,
            $settings,
            new NullLogger(),
            $authNotifications,
            new UserContextFactory($permissions),
            $clock,
        );
        $publicRegisterUseCase = new PublicRegisterUseCase(
            $users,
            $profiles,
            $roles,
            $organizations,
            $memberships,
            new PassthroughIamTransactionManager(),
            $subscriptionRepo,
            new InMemorySubscriptionPlanRepository([
                new SubscriptionPlan(
                    id: 1,
                    code: 'free',
                    name: 'Free Trial',
                    description: 'Free Trial Plan',
                    billingCycle: 'monthly',
                    price: 0.0,
                    currency: 'USD',
                    features: [],
                    isActive: true,
                    displayOrder: 1,
                    createdAt: new \DateTimeImmutable('2026-07-07 00:00:00'),
                    updatedAt: new \DateTimeImmutable('2026-07-07 00:00:00'),
                ),
            ]),
            $settings,
            $audit,
            $iamNotifications,
            $clock,
            new InMemoryEventPublisher(),
            new PlatformRoleResolver($roles),
        );

        $loginResult = $loginUseCase->execute(new LoginRequest(
            email: 'user@example.test',
            password: 'secret',
            ipAddress: '127.0.0.1',
        ));
        $createdUser = $publicRegisterUseCase->execute(new PublicRegisterRequest(
            email: 'new@example.test',
            fullName: 'New User',
            password: 'secret',
            organizationName: 'Acme Safety Group',
            phone: '+2348000000000',
            ipAddress: '127.0.0.1',
        ));

        self::assertSame('11111111-1111-4111-8111-111111111111', $loginResult->userUuid);
        self::assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $createdUser->getUuid());
        self::assertNotNull($memberships->membership);
        self::assertSame('worker', $memberships->membership?->getRoleSlug());
        self::assertSame('Acme Safety Group', $organizations->items[0]->getName());
    }

    public function test_public_registration_defaults_to_organization_admin_membership(): void
    {
        $users = new InMemoryIamUserRepository([]);
        $profiles = new InMemoryProfileRepository();
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository(null);
        $roles = new InMemoryRoleRepository([
            new Role(id: 1, uuid: '11111111-1111-4111-8111-111111111111', slug: 'organization_admin', name: 'Organization Admin', description: null, isSystem: false, scope: 'customer', permissions: ['organization.manage']),
            new Role(id: 2, uuid: '22222222-2222-4222-8222-222222222222', slug: 'member', name: 'Member', description: null, isSystem: true, scope: 'staff', permissions: ['iam.user.view']),
        ]);
        $registry = SettingsRegistry::fromProviders([new \WorkEddy\Modules\IAM\Settings\IAMSettingsProvider()]);
        $settings = new IAMSettings(new SettingsService([
            'iam.auth_otp_enabled' => false,
            'iam.password_algorithm' => 'bcrypt',
            'iam.argon2_memory_cost' => 65536,
            'iam.argon2_time_cost' => 4,
            'iam.argon2_threads' => 1,
            'iam.default_user_status_active' => true,
        ], $registry));

        $useCase = new PublicRegisterUseCase(
            $users,
            $profiles,
            $roles,
            $organizations,
            $memberships,
            new PassthroughIamTransactionManager(),
            new InMemorySubscriptionRepository(),
            new InMemorySubscriptionPlanRepository([
                new SubscriptionPlan(
                    id: 1,
                    code: 'free',
                    name: 'Free Trial',
                    description: 'Free Trial Plan',
                    billingCycle: 'monthly',
                    price: 0.0,
                    currency: 'USD',
                    features: [],
                    isActive: true,
                    displayOrder: 1,
                    createdAt: new \DateTimeImmutable('2026-07-07 00:00:00'),
                    updatedAt: new \DateTimeImmutable('2026-07-07 00:00:00'),
                ),
            ]),
            $settings,
            new RecordingIamAuditService(),
            new IAMNotificationDispatcher(new RecordingNotificationService(), $users, $settings),
            new FixedClock('2026-07-07 00:00:00'),
            new InMemoryEventPublisher(),
            new PlatformRoleResolver($roles),
        );

        $createdUser = $useCase->execute(new PublicRegisterRequest(
            email: 'owner@example.test',
            fullName: 'Workspace Owner',
            password: 'secret',
            organizationName: 'Acme Safety Group',
            phone: '+2348000000000',
            ipAddress: '127.0.0.1',
        ));

        self::assertSame('member', $createdUser->getRoleSlug());
        self::assertNotNull($memberships->membership);
        self::assertSame('organization_admin', $memberships->membership?->getRoleSlug());
    }

    public function test_resend_otp_refreshes_pending_auth_window_on_success(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE iam_otp_challenges (challenge_id TEXT PRIMARY KEY, user_id INTEGER, purpose TEXT, code_hash TEXT, expires_at TEXT, consumed_at TEXT NULL, created_at TEXT, updated_at TEXT)');

        $users = new InMemoryIamUserRepository([
            new User(
                id: 14,
                uuid: '11111111-1111-4111-8111-111111111111',
                email: 'user@example.test',
                fullName: 'User Example',
                passwordHash: password_hash('secret', PASSWORD_BCRYPT),
                roleId: 2,
                roleSlug: 'worker',
                status: UserStatus::ACTIVE,
                phone: '+2348000000000',
            ),
        ]);

        $settings = new IAMSettings(new SettingsService([
            'iam.password_algorithm' => 'bcrypt',
            'iam.argon2_memory_cost' => 65536,
            'iam.argon2_time_cost' => 4,
            'iam.argon2_threads' => 1,
            'iam.default_user_status_active' => true,
        ]));

        $session = new InMemorySessionService();
        $pendingExpiresAt = (new \DateTimeImmutable('@' . (time() + 120)))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('c');
        $session->set('pending_auth', [
            'userId' => 14,
            'email' => 'user@example.test',
            'challengeId' => 'challenge-1',
            'ipAddress' => '127.0.0.1',
            'issuedAt' => (new \DateTimeImmutable('@' . time()))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format('c'),
            'expiresAt' => $pendingExpiresAt,
        ]);

        $useCase = new ResendOTPUseCase(
            $users,
            new OTPRepository($connection, new ConfigLoader(), new FixedClock('2026-07-10 07:30:00')),
            new AuthenticationThrottleService(new InMemoryCacheService(), $settings),
            new RecordingIamAuditService(),
            $session,
            new IAMAuthNotificationDispatcher(new RecordingNotificationService(), new ConfigLoader()),
        );

        $result = $useCase->execute(14, '127.0.0.1');

        self::assertSame(14, $result['userId']);
        $pending = $session->get('pending_auth');
        self::assertIsArray($pending);
        self::assertNotSame($pendingExpiresAt, $pending['expiresAt']);
        self::assertGreaterThan(strtotime($pendingExpiresAt), strtotime((string) $pending['expiresAt']));
    }

    public function test_resend_otp_uses_authentication_exception_for_expired_window(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE iam_otp_challenges (challenge_id TEXT PRIMARY KEY, user_id INTEGER, purpose TEXT, code_hash TEXT, expires_at TEXT, consumed_at TEXT NULL, created_at TEXT, updated_at TEXT)');

        $users = new InMemoryIamUserRepository([
            new User(
                id: 14,
                uuid: '11111111-1111-4111-8111-111111111111',
                email: 'user@example.test',
                fullName: 'User Example',
                passwordHash: password_hash('secret', PASSWORD_BCRYPT),
                roleId: 2,
                roleSlug: 'worker',
                status: UserStatus::ACTIVE,
            ),
        ]);

        $settings = new IAMSettings(new SettingsService([
            'iam.password_algorithm' => 'bcrypt',
            'iam.argon2_memory_cost' => 65536,
            'iam.argon2_time_cost' => 4,
            'iam.argon2_threads' => 1,
            'iam.default_user_status_active' => true,
        ]));

        $session = new InMemorySessionService();
        $session->set('pending_auth', [
            'userId' => 14,
            'email' => 'user@example.test',
            'challengeId' => 'challenge-1',
            'ipAddress' => '127.0.0.1',
            'issuedAt' => (new \DateTimeImmutable('@' . (time() - 600)))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format('c'),
            'expiresAt' => (new \DateTimeImmutable('@' . (time() - 60)))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format('c'),
        ]);

        $useCase = new ResendOTPUseCase(
            $users,
            new OTPRepository($connection, new ConfigLoader(), new FixedClock('2026-07-10 07:30:00')),
            new AuthenticationThrottleService(new InMemoryCacheService(), $settings),
            new RecordingIamAuditService(),
            $session,
            new IAMAuthNotificationDispatcher(new RecordingNotificationService(), new ConfigLoader()),
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('OTP resend window has expired. Please log in again.');

        $useCase->execute(14, '127.0.0.1');
    }

    public function test_public_registration_fails_closed_without_allowed_roles(): void
    {
        $users = new InMemoryIamUserRepository([]);
        $profiles = new InMemoryProfileRepository();
        $roles = new InMemoryRoleRepository([
            new Role(id: 2, uuid: '22222222-2222-4222-8222-222222222222', slug: 'worker', name: 'Worker', description: null, isSystem: false, scope: 'customer', permissions: ['iam.user.view']),
        ]);
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository(null);
        $settings = new IAMSettings(new SettingsService([
            'iam.public_registration_allowed_roles' => [],
            'iam.password_algorithm' => 'bcrypt',
            'iam.argon2_memory_cost' => 65536,
            'iam.argon2_time_cost' => 4,
            'iam.argon2_threads' => 1,
            'iam.default_user_status_active' => true,
        ]));

        $useCase = new PublicRegisterUseCase(
            $users,
            $profiles,
            $roles,
            $organizations,
            $memberships,
            new PassthroughIamTransactionManager(),
            new InMemorySubscriptionRepository(),
            new InMemorySubscriptionPlanRepository([
                new SubscriptionPlan(
                    id: 1,
                    code: 'free',
                    name: 'Free Trial',
                    description: 'Free Trial Plan',
                    billingCycle: 'monthly',
                    price: 0.0,
                    currency: 'USD',
                    features: [],
                    isActive: true,
                    displayOrder: 1,
                    createdAt: new \DateTimeImmutable('2026-07-07 00:00:00'),
                    updatedAt: new \DateTimeImmutable('2026-07-07 00:00:00'),
                ),
            ]),
            $settings,
            new RecordingIamAuditService(),
            new IAMNotificationDispatcher(new RecordingNotificationService(), $users, $settings),
            new FixedClock('2026-07-07 00:00:00'),
            new InMemoryEventPublisher(),
            new PlatformRoleResolver($roles),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Public registration is disabled because no allowlisted roles are configured.');

        $useCase->execute(new PublicRegisterRequest(
            email: 'new@example.test',
            fullName: 'New User',
            password: 'secret',
            organizationName: 'Acme Safety Group',
            phone: '+2348000000000',
            ipAddress: '127.0.0.1',
        ));
    }

    public function test_pending_user_provisioning_requires_an_explicit_role_slug(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE iam_user_sources (user_id INTEGER, source_module TEXT, source_type TEXT, source_id TEXT, metadata_json TEXT NULL, created_at TEXT, updated_at TEXT)');

        $useCase = new ModuleUserProvisioner(
            new InMemoryIamUserRepository([]),
            new InMemoryProfileRepository(),
            new InMemoryMembershipRepository(null),
            new InMemoryRoleRepository([]),
            new PlatformRoleResolver(new InMemoryRoleRepository([])),
            $connection,
            new PassthroughIamTransactionManager(),
            new RecordingIamAuditService(),
            new FixedClock('2026-07-07 00:00:00'),
        );

        $this->expectException(\WorkEddy\Shared\Exceptions\ValidationException::class);

        $useCase->provisionPendingUser(
            sourceModule: 'task',
            sourceType: 'task',
            sourceId: 'task-1',
            email: 'new@example.test',
            fullName: 'New User',
        );
    }

    public function test_iam_permission_definitions_do_not_embed_default_role_assignments(): void
    {
        $provider = new IAMPermissionDefinitionProvider();

        foreach ($provider->definitions() as $definition) {
            self::assertSame([], $definition->defaultAssignments, $definition->key . ' should not declare default role assignments.');
        }
    }
}

final class InMemoryIamUserRepository implements IUserRepository
{
    /** @var array<int, User> */
    private array $items = [];

    /**
     * @param list<User> $users
     */
    public function __construct(array $users = [])
    {
        foreach ($users as $user) {
            if ($user->getId() !== null) {
                $this->items[(int) $user->getId()] = $user;
            }
        }
    }

    public function create(User $user): int|string
    {
        $id = $user->getId() ?? (count($this->items) + 1);
        $this->items[(int) $id] = new User(
            id: (int) $id,
            uuid: $user->getUuid(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            passwordHash: $user->getPasswordHash(),
            roleId: $user->getRoleId(),
            roleSlug: $user->getRoleSlug(),
            status: $user->getStatus(),
            phone: $user->getPhone(),
            authzVersion: $user->getAuthzVersion(),
        );

        return (int) $id;
    }

    public function update(User $user): void
    {
        $this->items[(int) $user->getId()] = $user;
    }

    public function findById(int|string $id): ?User
    {
        return $this->items[(int) $id] ?? null;
    }

    public function findByUuid(string $uuid): ?User
    {
        foreach ($this->items as $user) {
            if ($user->getUuid() === $uuid) {
                return $user;
            }
        }

        return null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->items as $user) {
            if (strtolower($user->getEmail()) === strtolower($email)) {
                return $user;
            }
        }

        return null;
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return array_slice(array_values($this->items), $offset, $limit);
    }

    public function count(array $filters = []): int
    {
        return count($this->items);
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
        $user = $this->items[(int) $userId];
        $user->bumpAuthzVersion();
        $this->items[(int) $userId] = $user;

        return $user->getAuthzVersion();
    }
}

final class InMemoryMembershipRepository implements IOrganizationMembershipRepository
{
    public function __construct(public ?OrganizationMembership $membership = null)
    {
    }

    public function create(OrganizationMembership $membership): int
    {
        $this->membership = $membership;

        return 1;
    }

    public function update(OrganizationMembership $membership): void
    {
        $this->membership = $membership;
    }

    public function delete(string $uuid): void
    {
        if ($this->membership !== null && $this->membership->getUuid() === $uuid) {
            $this->membership = null;
        }
    }

    public function findByUuid(string $uuid): ?OrganizationMembership
    {
        return $this->membership !== null && $this->membership->getUuid() === $uuid
            ? $this->membership
            : null;
    }

    public function findPrimaryByUserId(int|string $userId): ?OrganizationMembership
    {
        return $this->membership !== null && $this->membership->getUserId() === (int) $userId
            ? $this->membership
            : null;
    }

    public function findByUserAndOrganizationUuid(int|string $userId, string $organizationUuid): ?OrganizationMembership
    {
        return $this->membership !== null
            && $this->membership->getUserId() === (int) $userId
            && $this->membership->getOrganizationUuid() === $organizationUuid
            ? $this->membership
            : null;
    }

    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array
    {
        $items = $this->membership !== null && $this->membership->getOrganizationId() === $organizationId
            ? [$this->membership]
            : [];

        return array_slice($items, $offset, $limit);
    }

    public function findAllByUserId(int|string $userId): array
    {
        if ($this->membership === null || $this->membership->getUserId() !== (int) $userId) {
            return [];
        }

        return [$this->membership];
    }
}

final class InMemoryOrganizationRepository implements IOrganizationRepository
{
    /** @var list<Organization> */
    public array $items = [];

    public function create(Organization $organization): int
    {
        $this->items[] = new Organization(
            id: count($this->items) + 1,
            uuid: $organization->getUuid(),
            name: $organization->getName(),
            slug: $organization->getSlug(),
            status: $organization->getStatus(),
            contactEmail: $organization->getContactEmail(),
            phone: $organization->getPhone(),
            createdAt: $organization->getCreatedAt(),
        );

        return count($this->items);
    }

    public function update(Organization $organization): void
    {
        foreach ($this->items as $index => $item) {
            if ($item->getUuid() === $organization->getUuid()) {
                $this->items[$index] = $organization;
            }
        }
    }

    public function softDelete(string $uuid): void
    {
        $this->items = array_values(array_filter($this->items, static fn(Organization $organization): bool => $organization->getUuid() !== $uuid));
    }

    public function findById(int $id): ?Organization
    {
        return $this->items[$id - 1] ?? null;
    }

    public function findByUuid(string $uuid): ?Organization
    {
        foreach ($this->items as $item) {
            if ($item->getUuid() === $uuid) {
                return $item;
            }
        }

        return null;
    }

    public function findBySlug(string $slug): ?Organization
    {
        foreach ($this->items as $item) {
            if ($item->getSlug() === $slug) {
                return $item;
            }
        }

        return null;
    }

    public function findAll(int $limit = 50, int $offset = 0): array
    {
        return array_slice($this->items, $offset, $limit);
    }
}

final class InMemoryProfileRepository implements IUserProfileRepository
{
    public ?UserProfile $profile = null;

    public function create(UserProfile $profile): int
    {
        $this->profile = $profile;

        return 1;
    }

    public function update(UserProfile $profile): void
    {
        $this->profile = $profile;
    }

    public function findByUserId(int|string $userId): ?UserProfile
    {
        return $this->profile !== null && $this->profile->getUserId() === (int) $userId ? $this->profile : null;
    }
}

final class InMemoryRoleRepository implements IRoleRepository
{
    /** @var array<string, Role> */
    private array $rolesBySlug = [];

    /**
     * @param list<Role> $roles
     */
    public function __construct(array $roles)
    {
        foreach ($roles as $role) {
            $this->rolesBySlug[$role->getSlug()] = $role;
        }
    }

    public function findById(int|string $id): ?Role
    {
        foreach ($this->rolesBySlug as $role) {
            if ((int) $role->getId() === (int) $id) {
                return $role;
            }
        }

        return null;
    }

    public function findByUuid(string $uuid): ?Role
    {
        foreach ($this->rolesBySlug as $role) {
            if ($role->getUuid() === $uuid) {
                return $role;
            }
        }

        return null;
    }

    public function findBySlug(string $slug): ?Role
    {
        return $this->rolesBySlug[$slug] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->rolesBySlug);
    }

    public function create(Role $role): int|string
    {
        $this->rolesBySlug[$role->getSlug()] = $role;

        return (int) ($role->getId() ?? 0);
    }

    public function update(Role $role): void
    {
        $this->rolesBySlug[$role->getSlug()] = $role;
    }

    public function getPermissionsForRole(int|string $roleId): array
    {
        return [];
    }

    public function syncPermissions(int|string $roleId, array $permissionIds): void
    {
    }
}

final class NoopPermissionRepository implements IPermissionRepository
{
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
        return ['iam.user.update'];
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

final class AllowingPermissionService implements IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        if (!in_array($privilege, $ctx->privileges, true)) {
            throw new \RuntimeException('Missing expected privilege in test context: ' . $privilege);
        }
    }
}

final class PassthroughIamTransactionManager implements TransactionManagerInterface
{
    public function transactional(callable $callback): mixed
    {
        return $callback();
    }
}

final class RecordingIamAuditService implements IAuditService
{
    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
    }
}

final class InMemorySessionService implements \WorkEddy\Platform\Session\ISessionService
{
    private ?UserContext $context = null;
    private array $values = [];

    public function getUserContext(): ?UserContext
    {
        return $this->context;
    }

    public function setUserContext(UserContext $context): void
    {
        $this->context = $context;
    }

    public function regenerate(): void
    {
    }

    public function destroy(): void
    {
        $this->context = null;
        $this->values = [];
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }
}

final class InMemoryCacheService implements \WorkEddy\Platform\Cache\ICacheService
{
    private array $values = [];

    public function get(string $key, ?callable $compute = null, ?int $ttlSeconds = null): mixed
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        if ($compute !== null) {
            $this->values[$key] = $compute();
            return $this->values[$key];
        }

        return null;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->values[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->values[$key]);
    }

    public function deleteByTag(string $tag): void
    {
    }
}

final class FixedClock implements \WorkEddy\Platform\Clock\IClock
{
    public function __construct(private readonly string $now)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->now);
    }
}

final class RecordingNotificationService implements NotificationServiceInterface
{
    /** @var list<NotificationDomainRequest> */
    public array $requests = [];

    public function send(NotificationDomainRequest $request): NotificationDispatchResult
    {
        $this->requests[] = $request;

        return new NotificationDispatchResult(true, 'sent', 'notification-uuid');
    }
}

final class InMemorySubscriptionRepository implements ISubscriptionRepository
{
    public function __construct()
    {
    }

    public function createSubscription(array $data): Subscription
    {
        return new Subscription(
            id: 1,
            uuid: (string) ($data['uuid'] ?? '99999999-9999-4999-8999-999999999999'),
            organizationId: (int) ($data['organization_id'] ?? 0),
            organizationUuid: (string) ($data['organization_uuid'] ?? ''),
            planCode: (string) ($data['plan_code'] ?? ''),
            planName: (string) ($data['plan_name'] ?? ''),
            status: SubscriptionStatus::ACTIVE,
            billingCycle: (string) ($data['billing_cycle'] ?? 'monthly'),
            startDate: $this->toDateTimeImmutable($data['start_date'] ?? 'now'),
            expiryDate: null,
            activatedAt: $this->toDateTimeImmutable($data['activated_at'] ?? 'now'),
            suspendedAt: null,
            suspendedReason: null,
            cancelledAt: null,
            cancellationReason: null,
            autoRenew: true,
            createdAt: new \DateTimeImmutable('now'),
            updatedAt: new \DateTimeImmutable('now'),
        );
    }

    public function findSubscriptionByUuid(string $uuid): ?Subscription
    {
        return null;
    }

    public function findByOrganizationId(int $organizationId): ?Subscription
    {
        return null;
    }

    public function findActiveByOrganizationId(int $organizationId): ?Subscription
    {
        return null;
    }

    public function updateSubscription(Subscription $subscription, array $data): Subscription
    {
        return $subscription;
    }

    public function cancelSubscription(string $uuid, \DateTimeImmutable $cancelledAt, ?string $reason): Subscription
    {
        throw new \BadMethodCallException('Not used in this test.');
    }

    public function changePlan(string $uuid, string $newPlanCode, string $newPlanName, \DateTimeImmutable $effectiveDate): Subscription
    {
        throw new \BadMethodCallException('Not used in this test.');
    }

    public function listSubscriptions(array $filters = []): array
    {
        return [];
    }

    public function findDueForRenewal(\DateTimeImmutable $asOf): array
    {
        unset($asOf);

        return [];
    }

    private function toDateTimeImmutable(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        return new \DateTimeImmutable((string) $value);
    }
}

final class InMemorySubscriptionPlanRepository implements ISubscriptionPlanRepository
{
    /** @var array<string, SubscriptionPlan> */
    private array $plans = [];

    /**
     * @param list<SubscriptionPlan> $plans
     */
    public function __construct(array $plans = [])
    {
        foreach ($plans as $plan) {
            $this->plans[$plan->code] = $plan;
        }
    }

    public function findByCode(string $code): ?SubscriptionPlan
    {
        return $this->plans[$code] ?? null;
    }

    public function listActive(): array
    {
        return array_values(array_filter($this->plans, static fn(SubscriptionPlan $plan): bool => $plan->isActive));
    }

    public function listAll(): array
    {
        return array_values($this->plans);
    }

    public function upsert(array $data): SubscriptionPlan
    {
        $plan = new SubscriptionPlan(
            id: 1,
            code: (string) $data['code'],
            name: (string) $data['name'],
            description: $data['description'] ?? null,
            billingCycle: (string) ($data['billing_cycle'] ?? 'monthly'),
            price: (float) ($data['price'] ?? 0.0),
            currency: (string) ($data['currency'] ?? 'USD'),
            features: is_array($data['features'] ?? null) ? $data['features'] : [],
            isActive: (bool) ($data['is_active'] ?? true),
            displayOrder: (int) ($data['display_order'] ?? 1),
            createdAt: new \DateTimeImmutable('2026-07-07 00:00:00'),
            updatedAt: new \DateTimeImmutable('2026-07-07 00:00:00'),
        );
        $this->plans[$plan->code] = $plan;

        return $plan;
    }
}
