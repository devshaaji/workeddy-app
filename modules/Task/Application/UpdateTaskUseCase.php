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

final class UpdateTaskUseCase
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
        string $taskUuid,
        UserContext $actor,
        string $name,
        ?string $status = null,
        ?string $worksiteUuid = null,
        ?string $departmentUuid = null,
        ?string $jobRoleUuid = null,
        ?string $assessmentModel = null,
        ?string $taskCode = null,
        ?string $description = null,
    ): array {
        $this->permissions->requirePrivilege($actor, TaskPermissions::UPDATE);

        $organization = $this->organizations->findByUuid($organizationUuid);
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        $existing = $this->tasks->findByUuid($taskUuid);
        if ($existing === null || $existing->getOrganizationId() !== ($organization->getId() ?? 0)) {
            throw new NotFoundException('Task not found.');
        }

        $normalizedName = trim($name);
        if ($normalizedName === '') {
            throw new ValidationException(['name' => 'Task name is required.']);
        }

        $normalizedStatus = $status !== null ? trim($status) : $existing->getStatus();
        if (!in_array($normalizedStatus, ['active', 'inactive'], true)) {
            throw new ValidationException(['status' => 'Task status must be active or inactive.']);
        }
        $normalizedModel = $this->normalizeAssessmentModel($assessmentModel);

        $worksite = $this->resolveWorksite($organization->getId() ?? 0, $worksiteUuid);
        $department = $this->resolveDepartment($organization->getId() ?? 0, $departmentUuid);
        $jobRole = $this->resolveJobRole($organization->getId() ?? 0, $jobRoleUuid);

        $updated = new Task(
            id: $existing->getId(),
            uuid: $existing->getUuid(),
            organizationId: $existing->getOrganizationId(),
            worksiteId: $worksite?->getId(),
            departmentId: $department?->getId(),
            jobRoleId: $jobRole?->getId(),
            name: $normalizedName,
            assessmentModel: $normalizedModel,
            taskCode: $this->normalizeOptional($taskCode),
            status: $normalizedStatus,
            description: $this->normalizeOptional($description),
            createdAt: $existing->getCreatedAt(),
        );

        $this->tx->transactional(function () use ($existing, $updated, $actor): void {
            $this->tasks->update($updated);
            $this->audit->record(
                action: 'task.updated',
                entityType: 'Task',
                entityId: $updated->getUuid(),
                beforeState: [
                    'name' => $existing->getName(),
                    'assessmentModel' => $existing->getAssessmentModel(),
                    'status' => $existing->getStatus(),
                    'taskCode' => $existing->getTaskCode(),
                ],
                afterState: [
                    'name' => $updated->getName(),
                    'assessmentModel' => $updated->getAssessmentModel(),
                    'status' => $updated->getStatus(),
                    'taskCode' => $updated->getTaskCode(),
                ],
                actorId: (string) $actor->userId,
                actorType: 'User',
            );
        });

        return $this->views->make($updated);
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
