<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Organization;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Application\CreateDepartmentUseCase;
use WorkEddy\Modules\Organization\Application\CreateJobRoleUseCase;
use WorkEddy\Modules\Organization\Application\CreateWorksiteUseCase;
use WorkEddy\Modules\Organization\Application\EnrollPilotSiteUseCase;
use WorkEddy\Modules\Organization\Application\ListDepartmentsUseCase;
use WorkEddy\Modules\Organization\Application\ListJobRolesUseCase;
use WorkEddy\Modules\Organization\Application\ListPilotSitesUseCase;
use WorkEddy\Modules\Organization\Application\ListWorksitesUseCase;
use WorkEddy\Modules\Organization\Application\UpdateDepartmentUseCase;
use WorkEddy\Modules\Organization\Application\UpdateJobRoleUseCase;
use WorkEddy\Modules\Organization\Application\UpdatePilotSiteUseCase;
use WorkEddy\Modules\Organization\Application\UpdateWorksiteUseCase;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IPilotSiteRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Organization\Domain\Department;
use WorkEddy\Modules\Organization\Domain\JobRole;
use WorkEddy\Modules\Organization\Domain\Organization;
use WorkEddy\Modules\Organization\Domain\PilotSite;
use WorkEddy\Modules\Organization\Domain\Worksite;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionLimitGuard;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRecorder;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionUsage;
use WorkEddy\Modules\Subscription\Domain\ValueObjects\SubscriptionLimits;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\ValidationException;

final class OrganizationStructureUseCasesTest extends TestCase
{
    public function test_worksite_use_cases_create_list_and_update_with_audit(): void
    {
        $organizations = new InMemoryStructureOrganizationRepository([
            new Organization(
                id: 3,
                uuid: '11111111-1111-4111-8111-111111111111',
                name: 'Acme Safety Group',
                slug: 'acme-safety-group',
                status: 'active',
                contactEmail: 'ops@acme.test',
                phone: null,
                createdAt: '2026-07-07 00:00:00',
            ),
        ]);
        $worksites = new InMemoryWorksiteRepository();
        $audit = new RecordingAuditService();
        $usage = new StructureRecordingUsageRecorder();
        $actor = new UserContext(
            userId: 9,
            roleType: 'staff',
            privileges: [
                OrganizationPermissions::VIEW,
                OrganizationPermissions::STRUCTURE_MANAGE,
            ],
        );

        $create = new CreateWorksiteUseCase(
            $organizations,
            $worksites,
            new AllowAllStructurePermissionService(),
            new PassthroughStructureTransactionManager(),
            $audit,
            new StructureAllowingLimitGuard(),
            $usage,
        );
        $list = new ListWorksitesUseCase(
            $organizations,
            $worksites,
            new AllowAllStructurePermissionService(),
        );
        $update = new UpdateWorksiteUseCase(
            $organizations,
            $worksites,
            new AllowAllStructurePermissionService(),
            new PassthroughStructureTransactionManager(),
            $audit,
        );

        $created = $create->execute(
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            name: 'HQ Campus',
            actor: $actor,
            location: 'Ikeja',
        );

        self::assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $created['id']);
        self::assertSame('11111111-1111-4111-8111-111111111111', $created['organizationId']);
        self::assertSame('HQ Campus', $created['name']);
        self::assertSame('Ikeja', $created['location']);
        self::assertSame('active', $created['status']);
        self::assertSame(1, count($usage->records));
        self::assertSame('max_worksites', $usage->records[0]['metric']);

        $listed = $list->execute('11111111-1111-4111-8111-111111111111', $actor);

        self::assertCount(1, $listed);
        self::assertSame($created['id'], $listed[0]['id']);

        $updated = $update->execute(
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            worksiteUuid: $created['id'],
            actor: $actor,
            name: 'HQ Campus West',
            status: 'inactive',
            location: 'Yaba',
        );

        self::assertSame('HQ Campus West', $updated['name']);
        self::assertSame('inactive', $updated['status']);
        self::assertSame('Yaba', $updated['location']);
        self::assertSame(
            ['organization.worksite.created', 'organization.worksite.updated'],
            array_column($audit->records, 'action'),
        );
    }

    public function test_worksite_creation_blocks_when_plan_limit_would_be_exceeded(): void
    {
        $organizations = new InMemoryStructureOrganizationRepository([
            new Organization(
                id: 3,
                uuid: '11111111-1111-4111-8111-111111111111',
                name: 'Acme Safety Group',
                slug: 'acme-safety-group',
                status: 'active',
                contactEmail: 'ops@acme.test',
                phone: null,
                createdAt: '2026-07-07 00:00:00',
            ),
        ]);

        $create = new CreateWorksiteUseCase(
            $organizations,
            new InMemoryWorksiteRepository(),
            new AllowAllStructurePermissionService(),
            new PassthroughStructureTransactionManager(),
            new RecordingAuditService(),
            new StructureBlockingLimitGuard(),
            new StructureRecordingUsageRecorder(),
        );

        $this->expectException(ValidationException::class);

        $create->execute(
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            name: 'Overflow Site',
            actor: new UserContext(userId: 9, roleType: 'staff', privileges: [OrganizationPermissions::STRUCTURE_MANAGE]),
            location: null,
        );
    }

    public function test_department_use_cases_create_list_and_update_with_relationships(): void
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
        $organizations = new InMemoryStructureOrganizationRepository([$organization]);
        $worksites = new InMemoryWorksiteRepository([
            new Worksite(
                id: 10,
                uuid: '33333333-3333-4333-8333-333333333333',
                organizationId: 3,
                name: 'Lagos Yard',
                status: 'active',
                location: 'Apapa',
                createdAt: '2026-07-07 00:00:00',
            ),
        ]);
        $departments = new InMemoryDepartmentRepository([
            new Department(
                id: 11,
                uuid: '44444444-4444-4444-8444-444444444444',
                organizationId: 3,
                worksiteId: 10,
                parentDepartmentId: null,
                name: 'Operations',
                status: 'active',
                createdAt: '2026-07-07 00:00:00',
            ),
        ]);
        $audit = new RecordingAuditService();
        $actor = new UserContext(
            userId: 9,
            roleType: 'staff',
            privileges: [
                OrganizationPermissions::VIEW,
                OrganizationPermissions::STRUCTURE_MANAGE,
            ],
        );

        $create = new CreateDepartmentUseCase(
            $organizations,
            $departments,
            $worksites,
            new AllowAllStructurePermissionService(),
            new PassthroughStructureTransactionManager(),
            $audit,
        );
        $list = new ListDepartmentsUseCase(
            $organizations,
            $departments,
            $worksites,
            new AllowAllStructurePermissionService(),
        );
        $update = new UpdateDepartmentUseCase(
            $organizations,
            $departments,
            $worksites,
            new AllowAllStructurePermissionService(),
            new PassthroughStructureTransactionManager(),
            $audit,
        );

        $created = $create->execute(
            organizationUuid: $organization->getUuid(),
            name: 'Field Support',
            actor: $actor,
            worksiteUuid: '33333333-3333-4333-8333-333333333333',
        );

        self::assertSame($organization->getUuid(), $created['organizationId']);
        self::assertSame('33333333-3333-4333-8333-333333333333', $created['worksiteId']);
        self::assertNull($created['parentDepartmentId']);

        $listed = $list->execute($organization->getUuid(), $actor);

        self::assertCount(2, $listed);

        $updated = $update->execute(
            organizationUuid: $organization->getUuid(),
            departmentUuid: $created['id'],
            actor: $actor,
            name: 'Field Support West',
            status: 'inactive',
            worksiteUuid: '33333333-3333-4333-8333-333333333333',
            parentDepartmentUuid: '44444444-4444-4444-8444-444444444444',
        );

        self::assertSame('Field Support West', $updated['name']);
        self::assertSame('inactive', $updated['status']);
        self::assertSame('33333333-3333-4333-8333-333333333333', $updated['worksiteId']);
        self::assertSame('44444444-4444-4444-8444-444444444444', $updated['parentDepartmentId']);
        self::assertSame(
            ['organization.department.created', 'organization.department.updated'],
            array_column($audit->records, 'action'),
        );
    }

    public function test_job_role_use_cases_create_list_and_update_with_department_mapping(): void
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
        $organizations = new InMemoryStructureOrganizationRepository([$organization]);
        $departments = new InMemoryDepartmentRepository([
            new Department(
                id: 11,
                uuid: '44444444-4444-4444-8444-444444444444',
                organizationId: 3,
                worksiteId: null,
                parentDepartmentId: null,
                name: 'Operations',
                status: 'active',
                createdAt: '2026-07-07 00:00:00',
            ),
            new Department(
                id: 12,
                uuid: '55555555-5555-4555-8555-555555555555',
                organizationId: 3,
                worksiteId: null,
                parentDepartmentId: null,
                name: 'Safety',
                status: 'active',
                createdAt: '2026-07-07 00:00:00',
            ),
        ]);
        $roles = new InMemoryJobRoleRepository();
        $audit = new RecordingAuditService();
        $actor = new UserContext(
            userId: 9,
            roleType: 'staff',
            privileges: [
                OrganizationPermissions::VIEW,
                OrganizationPermissions::STRUCTURE_MANAGE,
            ],
        );

        $create = new CreateJobRoleUseCase(
            $organizations,
            $roles,
            $departments,
            new AllowAllStructurePermissionService(),
            new PassthroughStructureTransactionManager(),
            $audit,
        );
        $list = new ListJobRolesUseCase(
            $organizations,
            $roles,
            $departments,
            new AllowAllStructurePermissionService(),
        );
        $update = new UpdateJobRoleUseCase(
            $organizations,
            $roles,
            $departments,
            new AllowAllStructurePermissionService(),
            new PassthroughStructureTransactionManager(),
            $audit,
        );

        $created = $create->execute(
            organizationUuid: $organization->getUuid(),
            name: 'Safety Lead',
            actor: $actor,
            departmentUuid: '44444444-4444-4444-8444-444444444444',
        );

        self::assertSame($organization->getUuid(), $created['organizationId']);
        self::assertSame('44444444-4444-4444-8444-444444444444', $created['departmentId']);

        $listed = $list->execute($organization->getUuid(), $actor);

        self::assertCount(1, $listed);
        self::assertSame($created['id'], $listed[0]['id']);

        $updated = $update->execute(
            organizationUuid: $organization->getUuid(),
            jobRoleUuid: $created['id'],
            actor: $actor,
            name: 'Regional Safety Lead',
            status: 'inactive',
            departmentUuid: '55555555-5555-4555-8555-555555555555',
        );

        self::assertSame('Regional Safety Lead', $updated['name']);
        self::assertSame('inactive', $updated['status']);
        self::assertSame('55555555-5555-4555-8555-555555555555', $updated['departmentId']);
        self::assertSame(
            ['organization.job_role.created', 'organization.job_role.updated'],
            array_column($audit->records, 'action'),
        );
    }

    public function test_pilot_site_use_cases_enroll_list_and_update_with_industry_tracking(): void
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
        $organizations = new InMemoryStructureOrganizationRepository([$organization]);
        $worksites = new InMemoryWorksiteRepository([
            new Worksite(
                id: 10,
                uuid: '33333333-3333-4333-8333-333333333333',
                organizationId: 3,
                name: 'Lagos Yard',
                status: 'active',
                location: 'Apapa',
                createdAt: '2026-07-07 00:00:00',
            ),
        ]);
        $pilotSites = new InMemoryPilotSiteRepository();
        $audit = new RecordingAuditService();
        $actor = new UserContext(
            userId: 9,
            roleType: 'staff',
            privileges: [
                OrganizationPermissions::VIEW,
                OrganizationPermissions::STRUCTURE_MANAGE,
            ],
        );

        $enroll = new EnrollPilotSiteUseCase(
            $organizations,
            $worksites,
            $pilotSites,
            new AllowAllStructurePermissionService(),
            new PassthroughStructureTransactionManager(),
            $audit,
        );
        $list = new ListPilotSitesUseCase(
            $organizations,
            $pilotSites,
            new AllowAllStructurePermissionService(),
        );
        $update = new UpdatePilotSiteUseCase(
            $organizations,
            $pilotSites,
            new AllowAllStructurePermissionService(),
            new PassthroughStructureTransactionManager(),
            $audit,
        );

        $created = $enroll->execute(
            organizationUuid: $organization->getUuid(),
            worksiteUuid: '33333333-3333-4333-8333-333333333333',
            enrollmentDate: '2026-07-09',
            actor: $actor,
            pilotStatus: 'active',
            targetWorkerCount: 40,
            actualWorkerCount: 18,
            industry: 'Manufacturing',
            notes: 'Pilot phase one.',
        );

        self::assertSame('Manufacturing', $created['industry']);
        self::assertSame(40, $created['targetWorkerCount']);
        self::assertSame(18, $created['actualWorkerCount']);

        $listed = $list->execute($organization->getUuid(), $actor, ['industry' => 'Manufacturing']);
        self::assertCount(1, $listed);

        $updated = $update->execute(
            organizationUuid: $organization->getUuid(),
            pilotSiteUuid: $created['id'],
            actor: $actor,
            pilotStatus: 'completed',
            actualWorkerCount: 36,
            notes: 'Pilot closed with full reporting.',
        );

        self::assertSame('completed', $updated['pilotStatus']);
        self::assertSame(36, $updated['actualWorkerCount']);
        self::assertSame(
            ['organization.pilot_site.created', 'organization.pilot_site.updated'],
            array_column($audit->records, 'action'),
        );
    }
}

final class InMemoryStructureOrganizationRepository implements IOrganizationRepository
{
    /** @var array<string, Organization> */
    private array $items = [];

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
        $this->items[$organization->getUuid()] = $organization;

        return $organization->getId() ?? count($this->items);
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
}

final class InMemoryWorksiteRepository implements IWorksiteRepository
{
    /** @var array<string, Worksite> */
    private array $items = [];
    private int $nextId = 1;

    /**
     * @param list<Worksite> $worksites
     */
    public function __construct(array $worksites = [])
    {
        foreach ($worksites as $worksite) {
            $this->items[$worksite->getUuid()] = $worksite;
            $this->nextId = max($this->nextId, ($worksite->getId() ?? 0) + 1);
        }
    }

    public function create(Worksite $worksite): int
    {
        $id = $worksite->getId() ?? $this->nextId++;
        $this->items[$worksite->getUuid()] = new Worksite(
            id: $id,
            uuid: $worksite->getUuid(),
            organizationId: $worksite->getOrganizationId(),
            name: $worksite->getName(),
            status: $worksite->getStatus(),
            location: $worksite->getLocation(),
            createdAt: $worksite->getCreatedAt(),
        );

        return $id;
    }

    public function update(Worksite $worksite): void
    {
        $this->items[$worksite->getUuid()] = $worksite;
    }

    public function delete(string $uuid): void
    {
        unset($this->items[$uuid]);
    }

    public function findByUuid(string $uuid): ?Worksite
    {
        return $this->items[$uuid] ?? null;
    }

    public function findById(int $id): ?Worksite
    {
        foreach ($this->items as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
        }

        return null;
    }

    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array
    {
        $items = array_values(array_filter(
            $this->items,
            static fn(Worksite $worksite): bool => $worksite->getOrganizationId() === $organizationId,
        ));

        return array_slice($items, $offset, $limit);
    }
}

final class InMemoryDepartmentRepository implements IDepartmentRepository
{
    /** @var array<string, Department> */
    private array $items = [];
    private int $nextId = 1;

    /**
     * @param list<Department> $departments
     */
    public function __construct(array $departments = [])
    {
        foreach ($departments as $department) {
            $this->items[$department->getUuid()] = $department;
            $this->nextId = max($this->nextId, ($department->getId() ?? 0) + 1);
        }
    }

    public function create(Department $department): int
    {
        $id = $department->getId() ?? $this->nextId++;
        $this->items[$department->getUuid()] = new Department(
            id: $id,
            uuid: $department->getUuid(),
            organizationId: $department->getOrganizationId(),
            worksiteId: $department->getWorksiteId(),
            parentDepartmentId: $department->getParentDepartmentId(),
            name: $department->getName(),
            status: $department->getStatus(),
            createdAt: $department->getCreatedAt(),
        );

        return $id;
    }

    public function update(Department $department): void
    {
        $this->items[$department->getUuid()] = $department;
    }

    public function delete(string $uuid): void
    {
        unset($this->items[$uuid]);
    }

    public function findByUuid(string $uuid): ?Department
    {
        return $this->items[$uuid] ?? null;
    }

    public function findById(int $id): ?Department
    {
        foreach ($this->items as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
        }

        return null;
    }

    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array
    {
        $items = array_values(array_filter(
            $this->items,
            static fn(Department $department): bool => $department->getOrganizationId() === $organizationId,
        ));

        return array_slice($items, $offset, $limit);
    }
}

final class InMemoryPilotSiteRepository implements IPilotSiteRepository
{
    /** @var array<string, PilotSite> */
    private array $items = [];
    private int $nextId = 1;

    public function create(PilotSite $pilotSite): int
    {
        $id = $pilotSite->getId() ?? $this->nextId++;
        $this->items[$pilotSite->getUuid()] = new PilotSite(
            id: $id,
            uuid: $pilotSite->getUuid(),
            organizationId: $pilotSite->getOrganizationId(),
            organizationUuid: $pilotSite->getOrganizationUuid(),
            worksiteId: $pilotSite->getWorksiteId(),
            worksiteUuid: $pilotSite->getWorksiteUuid(),
            enrollmentDate: $pilotSite->getEnrollmentDate(),
            pilotStatus: $pilotSite->getPilotStatus(),
            targetWorkerCount: $pilotSite->getTargetWorkerCount(),
            actualWorkerCount: $pilotSite->getActualWorkerCount(),
            industry: $pilotSite->getIndustry(),
            notes: $pilotSite->getNotes(),
            createdAt: $pilotSite->getCreatedAt(),
        );

        return $id;
    }

    public function update(PilotSite $pilotSite): void
    {
        $this->items[$pilotSite->getUuid()] = $pilotSite;
    }

    public function findByUuid(string $uuid): ?PilotSite
    {
        return $this->items[$uuid] ?? null;
    }

    public function findAllByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $items = array_values(array_filter($this->items, static function (PilotSite $pilotSite) use ($organizationId, $filters): bool {
            if ($pilotSite->getOrganizationId() !== $organizationId) {
                return false;
            }
            if (($filters['worksiteUuid'] ?? null) && $pilotSite->getWorksiteUuid() !== $filters['worksiteUuid']) {
                return false;
            }
            if (($filters['pilotStatus'] ?? null) && $pilotSite->getPilotStatus() !== $filters['pilotStatus']) {
                return false;
            }
            if (($filters['industry'] ?? null) && $pilotSite->getIndustry() !== $filters['industry']) {
                return false;
            }

            return true;
        }));

        return array_slice($items, $offset, $limit);
    }
}

final class InMemoryJobRoleRepository implements IJobRoleRepository
{
    /** @var array<string, JobRole> */
    private array $items = [];
    private int $nextId = 1;

    /**
     * @param list<JobRole> $jobRoles
     */
    public function __construct(array $jobRoles = [])
    {
        foreach ($jobRoles as $jobRole) {
            $this->items[$jobRole->getUuid()] = $jobRole;
            $this->nextId = max($this->nextId, ($jobRole->getId() ?? 0) + 1);
        }
    }

    public function create(JobRole $jobRole): int
    {
        $id = $jobRole->getId() ?? $this->nextId++;
        $this->items[$jobRole->getUuid()] = new JobRole(
            id: $id,
            uuid: $jobRole->getUuid(),
            organizationId: $jobRole->getOrganizationId(),
            departmentId: $jobRole->getDepartmentId(),
            name: $jobRole->getName(),
            status: $jobRole->getStatus(),
            createdAt: $jobRole->getCreatedAt(),
        );

        return $id;
    }

    public function update(JobRole $jobRole): void
    {
        $this->items[$jobRole->getUuid()] = $jobRole;
    }

    public function delete(string $uuid): void
    {
        unset($this->items[$uuid]);
    }

    public function findByUuid(string $uuid): ?JobRole
    {
        return $this->items[$uuid] ?? null;
    }

    public function findById(int $id): ?JobRole
    {
        foreach ($this->items as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
        }

        return null;
    }

    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array
    {
        $items = array_values(array_filter(
            $this->items,
            static fn(JobRole $jobRole): bool => $jobRole->getOrganizationId() === $organizationId,
        ));

        return array_slice($items, $offset, $limit);
    }
}

final class AllowAllStructurePermissionService implements IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        if (!in_array($privilege, $ctx->privileges, true)) {
            throw new \RuntimeException('Missing expected privilege in test context: ' . $privilege);
        }
    }
}

final class PassthroughStructureTransactionManager implements TransactionManagerInterface
{
    public function transactional(callable $callback): mixed
    {
        return $callback();
    }
}

final class StructureAllowingLimitGuard implements ISubscriptionLimitGuard
{
    public function forOrganization(int $organizationId, string $metric): SubscriptionLimits
    {
        return SubscriptionLimits::fromValues($metric, 5, 1);
    }

    public function wouldExceed(int $organizationId, string $metric, int $increment = 1): bool
    {
        return false;
    }
}

final class StructureBlockingLimitGuard implements ISubscriptionLimitGuard
{
    public function forOrganization(int $organizationId, string $metric): SubscriptionLimits
    {
        return SubscriptionLimits::fromValues($metric, 1, 1);
    }

    public function wouldExceed(int $organizationId, string $metric, int $increment = 1): bool
    {
        return true;
    }
}

final class StructureRecordingUsageRecorder implements ISubscriptionUsageRecorder
{
    public array $records = [];

    public function forOrganization(int $organizationId, string $metric, int $increment = 1): SubscriptionUsage
    {
        $this->records[] = compact('organizationId', 'metric', 'increment');

        return new SubscriptionUsage(
            subscriptionUuid: 'sub-structure',
            periodStart: new \DateTimeImmutable('2026-07-01'),
            periodEnd: new \DateTimeImmutable('2026-07-31'),
            usageData: [$metric => $increment],
            updatedAt: new \DateTimeImmutable('2026-07-08 10:00:00'),
        );
    }
}

final class RecordingAuditService implements IAuditService
{
    /** @var list<array<string, mixed>> */
    public array $records = [];

    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
        $this->records[] = [
            'action' => $action,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'beforeState' => $beforeState,
            'afterState' => $afterState,
            'actorId' => $actorId,
            'actorType' => $actorType,
            'idempotencyKey' => $idempotencyKey,
            'metadata' => $metadata,
        ];
    }
}
