<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Task;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Organization\Domain\Department;
use WorkEddy\Modules\Organization\Domain\JobRole;
use WorkEddy\Modules\Organization\Domain\Organization;
use WorkEddy\Modules\Organization\Domain\Worksite;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\Ergonomics\Domain\Services\NioshService;
use WorkEddy\Modules\Ergonomics\Domain\Services\RebaService;
use WorkEddy\Modules\Ergonomics\Domain\Services\RulaService;
use WorkEddy\Modules\Task\Application\CreateTaskUseCase;
use WorkEddy\Modules\Task\Application\ListTasksUseCase;
use WorkEddy\Modules\Task\Application\UpdateTaskUseCase;
use WorkEddy\Modules\Task\Authorization\TaskPermissions;
use WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository;
use WorkEddy\Modules\Task\Domain\Task;
use WorkEddy\Platform\Settings\ModuleSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;

final class TaskModuleTest extends TestCase
{
    public function test_service_providers_expose_settings_providers(): void
    {
        $organizationProvider = new \WorkEddy\Modules\Organization\ServiceProvider();
        $taskProvider = new \WorkEddy\Modules\Task\ServiceProvider();

        self::assertNotNull($organizationProvider->getSettingsProvider());
        self::assertSame('organization', $organizationProvider->getSettingsProvider()?->getModuleName());
        self::assertNotSame([], $organizationProvider->getSettingsProvider()?->getDefinitions());

        self::assertNotNull($taskProvider->getSettingsProvider());
        self::assertSame('task', $taskProvider->getSettingsProvider()?->getModuleName());
        self::assertNotSame([], $taskProvider->getSettingsProvider()?->getDefinitions());

        self::assertTrue(is_subclass_of(\WorkEddy\Modules\Organization\Settings\OrganizationSettings::class, ModuleSettings::class));
        self::assertTrue(is_subclass_of(\WorkEddy\Modules\Task\Settings\TaskSettings::class, ModuleSettings::class));
    }

    public function test_task_service_provider_registers_page_and_api_controllers(): void
    {
        $definitions = (new \WorkEddy\Modules\Task\ServiceProvider())->getDefinitions();

        self::assertArrayHasKey(\WorkEddy\Modules\Task\Presentation\TaskController::class, $definitions);
        self::assertArrayHasKey(\WorkEddy\Modules\Task\Presentation\TaskPageController::class, $definitions);
        self::assertArrayHasKey(\WorkEddy\Modules\Task\Presentation\TaskPageData::class, $definitions);
    }

    public function test_task_use_cases_create_list_and_update_with_scope_mapping(): void
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
        $organizations = new InMemoryTaskOrganizationRepository([$organization]);
        $worksites = new InMemoryTaskWorksiteRepository([
            new Worksite(
                id: 10,
                uuid: '22222222-2222-4222-8222-222222222222',
                organizationId: 3,
                name: 'Lagos Yard',
                status: 'active',
                location: 'Apapa',
                createdAt: '2026-07-07 00:00:00',
            ),
        ]);
        $departments = new InMemoryTaskDepartmentRepository([
            new Department(
                id: 11,
                uuid: '33333333-3333-4333-8333-333333333333',
                organizationId: 3,
                worksiteId: 10,
                parentDepartmentId: null,
                name: 'Operations',
                status: 'active',
                createdAt: '2026-07-07 00:00:00',
            ),
        ]);
        $jobRoles = new InMemoryTaskJobRoleRepository([
            new JobRole(
                id: 12,
                uuid: '44444444-4444-4444-8444-444444444444',
                organizationId: 3,
                departmentId: 11,
                name: 'Loader',
                status: 'active',
                createdAt: '2026-07-07 00:00:00',
            ),
        ]);
        $tasks = new InMemoryTaskRepository();
        $audit = new RecordingTaskAuditService();
        $actor = new UserContext(
            userId: 9,
            roleType: 'staff',
            privileges: [
                TaskPermissions::VIEW,
                TaskPermissions::CREATE,
                TaskPermissions::UPDATE,
            ],
        );

        $create = new CreateTaskUseCase(
            $organizations,
            $tasks,
            $worksites,
            $departments,
            $jobRoles,
            new AssessmentEngine([new RebaService(), new RulaService(), new NioshService()]),
            new AllowAllTaskPermissionService(),
            new PassthroughTaskTransactionManager(),
            $audit,
        );
        $list = new ListTasksUseCase(
            $organizations,
            $tasks,
            $worksites,
            $departments,
            $jobRoles,
            new AssessmentEngine([new RebaService(), new RulaService(), new NioshService()]),
            new AllowAllTaskPermissionService(),
        );
        $update = new UpdateTaskUseCase(
            $organizations,
            $tasks,
            $worksites,
            $departments,
            $jobRoles,
            new AssessmentEngine([new RebaService(), new RulaService(), new NioshService()]),
            new AllowAllTaskPermissionService(),
            new PassthroughTaskTransactionManager(),
            $audit,
        );

        $created = $create->execute(
            organizationUuid: $organization->getUuid(),
            name: 'Container Lift',
            actor: $actor,
            worksiteUuid: '22222222-2222-4222-8222-222222222222',
            departmentUuid: '33333333-3333-4333-8333-333333333333',
            jobRoleUuid: '44444444-4444-4444-8444-444444444444',
            assessmentModel: 'reba',
            taskCode: 'CL-001',
            description: 'Manual handling at outbound dock.',
        );

        self::assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $created['id']);
        self::assertSame($organization->getUuid(), $created['organizationId']);
        self::assertSame('22222222-2222-4222-8222-222222222222', $created['worksiteId']);
        self::assertSame('33333333-3333-4333-8333-333333333333', $created['departmentId']);
        self::assertSame('44444444-4444-4444-8444-444444444444', $created['jobRoleId']);
        self::assertSame('reba', $created['assessmentModel']);
        self::assertSame(['manual', 'video'], $created['supportedInputTypes']);
        self::assertTrue($created['supportsVideo']);
        self::assertSame('CL-001', $created['taskCode']);
        self::assertSame('active', $created['status']);

        $listed = $list->execute($organization->getUuid(), $actor);

        self::assertCount(1, $listed);
        self::assertSame($created['id'], $listed[0]['id']);

        $updated = $update->execute(
            organizationUuid: $organization->getUuid(),
            taskUuid: $created['id'],
            actor: $actor,
            name: 'Container Lift Night Shift',
            status: 'inactive',
            worksiteUuid: '22222222-2222-4222-8222-222222222222',
            departmentUuid: '33333333-3333-4333-8333-333333333333',
            jobRoleUuid: '44444444-4444-4444-8444-444444444444',
            assessmentModel: 'niosh',
            taskCode: 'CL-002',
            description: 'Night shift manual handling at outbound dock.',
        );

        self::assertSame('Container Lift Night Shift', $updated['name']);
        self::assertSame('niosh', $updated['assessmentModel']);
        self::assertSame(['manual'], $updated['supportedInputTypes']);
        self::assertFalse($updated['supportsVideo']);
        self::assertSame('inactive', $updated['status']);
        self::assertSame('CL-002', $updated['taskCode']);
        self::assertSame(
            ['task.created', 'task.updated'],
            array_column($audit->records, 'action'),
        );
    }
}

final class InMemoryTaskOrganizationRepository implements IOrganizationRepository
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

final class InMemoryTaskWorksiteRepository implements IWorksiteRepository
{
    /** @var array<string, Worksite> */
    private array $items = [];

    /**
     * @param list<Worksite> $worksites
     */
    public function __construct(array $worksites = [])
    {
        foreach ($worksites as $worksite) {
            $this->items[$worksite->getUuid()] = $worksite;
        }
    }

    public function create(Worksite $worksite): int
    {
        $this->items[$worksite->getUuid()] = $worksite;

        return $worksite->getId() ?? count($this->items);
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

final class InMemoryTaskDepartmentRepository implements IDepartmentRepository
{
    /** @var array<string, Department> */
    private array $items = [];

    /**
     * @param list<Department> $departments
     */
    public function __construct(array $departments = [])
    {
        foreach ($departments as $department) {
            $this->items[$department->getUuid()] = $department;
        }
    }

    public function create(Department $department): int
    {
        $this->items[$department->getUuid()] = $department;

        return $department->getId() ?? count($this->items);
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

final class InMemoryTaskJobRoleRepository implements IJobRoleRepository
{
    /** @var array<string, JobRole> */
    private array $items = [];

    /**
     * @param list<JobRole> $jobRoles
     */
    public function __construct(array $jobRoles = [])
    {
        foreach ($jobRoles as $jobRole) {
            $this->items[$jobRole->getUuid()] = $jobRole;
        }
    }

    public function create(JobRole $jobRole): int
    {
        $this->items[$jobRole->getUuid()] = $jobRole;

        return $jobRole->getId() ?? count($this->items);
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

final class InMemoryTaskRepository implements ITaskRepository
{
    /** @var array<string, Task> */
    private array $items = [];
    private int $nextId = 1;

    public function create(Task $task): int
    {
        $id = $task->getId() ?? $this->nextId++;
        $this->items[$task->getUuid()] = new Task(
            id: $id,
            uuid: $task->getUuid(),
            organizationId: $task->getOrganizationId(),
            worksiteId: $task->getWorksiteId(),
            departmentId: $task->getDepartmentId(),
            jobRoleId: $task->getJobRoleId(),
            name: $task->getName(),
            assessmentModel: $task->getAssessmentModel(),
            taskCode: $task->getTaskCode(),
            status: $task->getStatus(),
            description: $task->getDescription(),
            createdAt: $task->getCreatedAt(),
        );

        return $id;
    }

    public function update(Task $task): void
    {
        $this->items[$task->getUuid()] = $task;
    }

    public function delete(string $uuid): void
    {
        unset($this->items[$uuid]);
    }

    public function findByUuid(string $uuid): ?Task
    {
        return $this->items[$uuid] ?? null;
    }

    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array
    {
        $items = array_values(array_filter(
            $this->items,
            static fn(Task $task): bool => $task->getOrganizationId() === $organizationId,
        ));

        return array_slice($items, $offset, $limit);
    }
}

final class AllowAllTaskPermissionService implements IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        if (!in_array($privilege, $ctx->privileges, true)) {
            throw new \RuntimeException('Missing expected privilege in test context: ' . $privilege);
        }
    }
}

final class PassthroughTaskTransactionManager implements TransactionManagerInterface
{
    public function transactional(callable $callback): mixed
    {
        return $callback();
    }
}

final class RecordingTaskAuditService implements IAuditService
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
