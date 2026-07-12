<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Task\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\Task\Authorization\TaskPermissions;
use WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository;
use WorkEddy\Modules\Task\Domain\Task;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class CreateTaskUseCase
{
    private readonly TaskViewFactory $views;

    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly ITaskRepository $tasks,
        private readonly IWorksiteRepository $worksites,
        private readonly IDepartmentRepository $departments,
        private readonly IJobRoleRepository $jobRoles,
        private readonly AssessmentEngine $engine,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
    ) {
        $this->views = new TaskViewFactory($organizations, $worksites, $departments, $jobRoles, $engine);
    }

    public function execute(
        string $organizationUuid,
        string $name,
        UserContext $actor,
        ?string $worksiteUuid = null,
        ?string $departmentUuid = null,
        ?string $jobRoleUuid = null,
        ?string $assessmentModel = null,
        ?string $taskCode = null,
        ?string $description = null,
    ): array {
        $this->permissions->requirePrivilege($actor, TaskPermissions::CREATE);

        $organization = $this->organizations->findByUuid($organizationUuid);
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        $normalizedName = trim($name);
        if ($normalizedName === '') {
            throw new ValidationException(['name' => 'Task name is required.']);
        }
        $normalizedModel = $this->normalizeAssessmentModel($assessmentModel);

        $worksite = $this->resolveWorksite($organization->getId() ?? 0, $worksiteUuid);
        $department = $this->resolveDepartment($organization->getId() ?? 0, $departmentUuid);
        $jobRole = $this->resolveJobRole($organization->getId() ?? 0, $jobRoleUuid);

        $task = new Task(
            id: null,
            uuid: UuidSupport::generate(),
            organizationId: $organization->getId() ?? 0,
            worksiteId: $worksite?->getId(),
            departmentId: $department?->getId(),
            jobRoleId: $jobRole?->getId(),
            name: $normalizedName,
            assessmentModel: $normalizedModel,
            taskCode: $this->normalizeOptional($taskCode),
            status: 'active',
            description: $this->normalizeOptional($description),
        );

        $this->tx->transactional(function () use ($task, $actor): void {
            $this->tasks->create($task);
            $this->audit->record(
                action: 'task.created',
                entityType: 'Task',
                entityId: $task->getUuid(),
                afterState: [
                    'name' => $task->getName(),
                    'assessmentModel' => $task->getAssessmentModel(),
                    'status' => $task->getStatus(),
                    'taskCode' => $task->getTaskCode(),
                ],
                actorId: (string) $actor->userId,
                actorType: 'User',
            );
        });

        return $this->views->make($task);
    }

    private function resolveWorksite(int $organizationId, ?string $worksiteUuid): ?\WorkEddy\Modules\Organization\Domain\Worksite
    {
        if ($worksiteUuid === null || trim($worksiteUuid) === '') {
            return null;
        }

        $worksite = $this->worksites->findByUuid(trim($worksiteUuid));
        if ($worksite === null || $worksite->getOrganizationId() !== $organizationId) {
            throw new NotFoundException('Worksite not found for organization.');
        }

        return $worksite;
    }

    private function resolveDepartment(int $organizationId, ?string $departmentUuid): ?\WorkEddy\Modules\Organization\Domain\Department
    {
        if ($departmentUuid === null || trim($departmentUuid) === '') {
            return null;
        }

        $department = $this->departments->findByUuid(trim($departmentUuid));
        if ($department === null || $department->getOrganizationId() !== $organizationId) {
            throw new NotFoundException('Department not found for organization.');
        }

        return $department;
    }

    private function resolveJobRole(int $organizationId, ?string $jobRoleUuid): ?\WorkEddy\Modules\Organization\Domain\JobRole
    {
        if ($jobRoleUuid === null || trim($jobRoleUuid) === '') {
            return null;
        }

        $jobRole = $this->jobRoles->findByUuid(trim($jobRoleUuid));
        if ($jobRole === null || $jobRole->getOrganizationId() !== $organizationId) {
            throw new NotFoundException('Job role not found for organization.');
        }

        return $jobRole;
    }

    private function normalizeOptional(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeAssessmentModel(?string $value): string
    {
        $model = strtolower(trim((string) $value));
        if ($model === '') {
            throw new ValidationException(['assessmentModel' => 'Assessment model is required for the task.']);
        }
        if (!in_array($model, $this->engine->availableModels(), true)) {
            throw new ValidationException(['assessmentModel' => 'Assessment model must be REBA, RULA, or NIOSH.']);
        }

        return $model;
    }
}
