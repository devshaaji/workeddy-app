<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Organization;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Modules\Organization\Application\CreateOrganizationUseCase;
use WorkEddy\Modules\Organization\Application\InviteOrganizationMemberUseCase;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Organization;
use WorkEddy\Modules\IAM\Application\Services\ModuleUserProvisionerInterface;
use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Modules\IAM\Domain\UserStatus;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionLimitGuard;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRecorder;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionUsage;
use WorkEddy\Modules\Subscription\Domain\ValueObjects\SubscriptionLimits;
use WorkEddy\Platform\Events\InMemoryEventPublisher;

final class OrganizationUseCasesTest extends TestCase
{
    public function test_create_organization_generates_slug_and_returns_public_uuid(): void
    {
        $repo = new InMemoryOrganizationRepository();
        $useCase = new CreateOrganizationUseCase(
            $repo,
            new AllowAllPermissionService(),
            new PassthroughTransactionManager(),
            new NullAuditService(),
            new InMemoryEventPublisher(),
        );

        $result = $useCase->execute(
            name: 'Acme Safety Group',
            contactEmail: 'ops@acme.test',
            actor: new UserContext(userId: 9, roleType: 'super_admin', privileges: ['organization.manage']),
        );

        self::assertSame('Acme Safety Group', $result['name']);
        self::assertSame('acme-safety-group', $result['slug']);
        self::assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $result['id']);
        self::assertSame($result['id'], $repo->lastCreated()?->getUuid());
    }

    public function test_invite_organization_member_uses_membership_scoped_provisioning(): void
    {
        $organization = new Organization(
            id: 3,
            uuid: '11111111-1111-4111-8111-111111111111',
            name: 'Acme Safety Group',
            slug: 'acme-safety-group',
            status: 'active',
            contactEmail: 'ops@acme.test',
            phone: null,
            createdAt: '2026-07-07 00:00:00',
        );
        $repo = new InMemoryOrganizationRepository([$organization]);
        $provisioner = new RecordingProvisioner();
        $useCase = new InviteOrganizationMemberUseCase(
            $repo,
            $provisioner,
            new AllowAllPermissionService(),
            new NullAuditService(),
            new OrganizationAllowingLimitGuard(),
            new OrganizationRecordingUsageRecorder(),
        );

        $result = $useCase->execute(
            organizationUuid: $organization->getUuid(),
            email: 'reviewer@acme.test',
            fullName: 'Reviewer One',
            phone: '+2348000000000',
            roleSlug: 'external_reviewer',
            actor: new UserContext(userId: 9, roleType: 'super_admin', privileges: ['organization.members.manage']),
        );

        self::assertSame('22222222-2222-4222-8222-222222222222', $result['userId']);
        self::assertSame($organization->getUuid(), $provisioner->organizationUuid);
        self::assertSame('external_reviewer', $provisioner->roleSlug);
        self::assertSame('reviewer@acme.test', $provisioner->email);
    }
}

final class OrganizationAllowingLimitGuard implements ISubscriptionLimitGuard
{
    public function forOrganization(int $organizationId, string $metric): SubscriptionLimits
    {
        return SubscriptionLimits::fromValues($metric, 10, 0);
    }

    public function wouldExceed(int $organizationId, string $metric, int $increment = 1): bool
    {
        return false;
    }
}

final class OrganizationRecordingUsageRecorder implements ISubscriptionUsageRecorder
{
    public array $records = [];

    public function forOrganization(int $organizationId, string $metric, int $increment = 1): SubscriptionUsage
    {
        $this->records[] = compact('organizationId', 'metric', 'increment');

        return new SubscriptionUsage(
            subscriptionUuid: 'sub-test',
            periodStart: new \DateTimeImmutable('2026-07-01'),
            periodEnd: new \DateTimeImmutable('2026-07-31'),
            usageData: [$metric => $increment],
            updatedAt: new \DateTimeImmutable('2026-07-08 10:00:00'),
        );
    }
}

final class InMemoryOrganizationRepository implements IOrganizationRepository
{
    /** @var array<string, Organization> */
    private array $items = [];
    private ?Organization $lastCreated = null;

    /**
     * @param list<Organization> $organizations
     */
    public function __construct(array $organizations = [])
    {
        foreach ($organizations as $organization) {
            $this->items[$organization->getUuid()] = $organization;
        }
    }

    public function create(Organization $organization): int
    {
        $this->lastCreated = $organization;
        $id = $organization->getId() ?? (count($this->items) + 1);
        $this->items[$organization->getUuid()] = $organization;

        return $id;
    }

    public function update(Organization $organization): void
    {
        $this->items[$organization->getUuid()] = $organization;
    }

    public function findById(int $id): ?Organization
    {
        foreach ($this->items as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
        }

        return null;
    }

    public function findByUuid(string $uuid): ?Organization
    {
        return $this->items[$uuid] ?? null;
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
        return array_slice(array_values($this->items), $offset, $limit);
    }

    public function softDelete(string $uuid): void
    {
        unset($this->items[$uuid]);
    }

    public function lastCreated(): ?Organization
    {
        return $this->lastCreated;
    }
}

final class AllowAllPermissionService implements IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        if (!in_array($privilege, $ctx->privileges, true)) {
            throw new \RuntimeException('Missing expected privilege in test context: ' . $privilege);
        }
    }
}

final class PassthroughTransactionManager implements TransactionManagerInterface
{
    public function transactional(callable $callback): mixed
    {
        return $callback();
    }
}

final class NullAuditService implements IAuditService
{
    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
    }
}

final class RecordingProvisioner implements ModuleUserProvisionerInterface
{
    public ?string $organizationUuid = null;
    public ?string $roleSlug = null;
    public ?string $email = null;

    public function provisionInvitedUser(
        string $sourceModule,
        string $sourceType,
        string $sourceId,
        string $email,
        string $fullName,
        ?string $phone,
        string $roleSlug,
        ?string $actorId = null,
        array $metadata = [],
        string $requiredRoleScope = 'staff',
        ?string $organizationUuid = null,
    ): User {
        $this->organizationUuid = $organizationUuid;
        $this->roleSlug = $roleSlug;
        $this->email = $email;

        return new User(
            id: 77,
            uuid: '22222222-2222-4222-8222-222222222222',
            email: $email,
            fullName: $fullName,
            passwordHash: 'hash',
            roleId: 5,
            roleSlug: $roleSlug,
            status: UserStatus::ACTIVE,
            phone: $phone,
        );
    }

    public function provisionPendingUser(
        string $sourceModule,
        string $sourceType,
        string $sourceId,
        string $email,
        string $fullName,
        ?string $phone = null,
        ?string $roleSlug = null,
        ?string $actorId = null,
        array $metadata = [],
    ): User {
        throw new \BadMethodCallException('Not used in this test.');
    }
}
