<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Task\Presentation;

use WorkEddy\Modules\Task\Application\CreateTaskUseCase;
use WorkEddy\Modules\Task\Application\ListTasksUseCase;
use WorkEddy\Modules\Task\Application\UpdateTaskUseCase;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Task\Application\TaskViewFactory;
use WorkEddy\Modules\Task\Authorization\TaskPermissions;
use WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class TaskController
{
    public function __construct(
        private readonly CreateTaskUseCase $createTask,
        private readonly ListTasksUseCase $listTasks,
        private readonly UpdateTaskUseCase $updateTask,
        private readonly ISessionService $session,
        private readonly IAuditService $audit,
        private readonly ?ITaskRepository $tasks = null,
        private readonly ?IOrganizationRepository $organizations = null,
        private readonly ?IPermissionService $permissions = null,
        private readonly ?TaskViewFactory $views = null,
    ) {}

    public function list(Request $request): Response
    {
        $ctx = $this->requireContext();

        return Response::json(['status' => 'ok', 'data' => $this->listTasks->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            actor: $ctx,
            limit: max(1, min(100, (int) ($request->query('limit') ?? 50))),
            offset: max(0, (int) ($request->query('offset') ?? 0)),
        )]);
    }

    public function create(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->createTask->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            name: (string) ($body['name'] ?? ''),
            actor: $ctx,
            worksiteUuid: $this->stringOrNull($body, 'worksiteId', 'worksite_id'),
            departmentUuid: $this->stringOrNull($body, 'departmentId', 'department_id'),
            jobRoleUuid: $this->stringOrNull($body, 'jobRoleId', 'job_role_id'),
            assessmentModel: $this->stringOrNull($body, 'assessmentModel', 'assessment_model'),
            taskCode: $this->stringOrNull($body, 'taskCode', 'task_code'),
            description: isset($body['description']) ? (string) $body['description'] : null,
        )], 201);
    }

    public function show(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->requirePermissions()->requirePrivilege($ctx, TaskPermissions::VIEW);
        $organization = $this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx);
        $task = $this->requireTasks()->findByUuid((string) ($request->routeParam('taskId') ?? ''));
        if ($task === null || $task->getOrganizationId() !== $organization->getId()) {
            throw new NotFoundException('Task not found.');
        }

        return Response::json(['status' => 'ok', 'data' => $this->serializeTask($task)]);
    }

    public function update(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->updateTask->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            taskUuid: (string) ($request->routeParam('taskId') ?? ''),
            actor: $ctx,
            name: (string) ($body['name'] ?? ''),
            status: isset($body['status']) ? (string) $body['status'] : null,
            worksiteUuid: $this->stringOrNull($body, 'worksiteId', 'worksite_id'),
            departmentUuid: $this->stringOrNull($body, 'departmentId', 'department_id'),
            jobRoleUuid: $this->stringOrNull($body, 'jobRoleId', 'job_role_id'),
            assessmentModel: $this->stringOrNull($body, 'assessmentModel', 'assessment_model'),
            taskCode: $this->stringOrNull($body, 'taskCode', 'task_code'),
            description: isset($body['description']) ? (string) $body['description'] : null,
        )]);
    }

    public function delete(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->requirePermissions()->requirePrivilege($ctx, TaskPermissions::UPDATE);
        $organization = $this->requireOrganization((string) ($request->routeParam('id') ?? ''), $ctx);
        $tasks = $this->requireTasks();
        $task = $tasks->findByUuid((string) ($request->routeParam('taskId') ?? ''));
        if ($task === null || $task->getOrganizationId() !== $organization->getId()) {
            throw new NotFoundException('Task not found.');
        }
        $before = $this->serializeTask($task);
        $tasks->delete($task->getUuid());
        $this->audit->record('task.deleted', 'task', $task->getUuid(), beforeState: $before, afterState: ['id' => $task->getUuid(), 'deleted' => true], actorId: (string) $ctx->userId, actorType: 'user');

        return Response::json(['status' => 'ok', 'data' => ['id' => $task->getUuid(), 'deleted' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(Request $request): array
    {
        return array_replace($request->body, $request->json);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function stringOrNull(array $body, string $camelKey, string $snakeKey): ?string
    {
        if (isset($body[$camelKey])) {
            return (string) $body[$camelKey];
        }

        if (isset($body[$snakeKey])) {
            return (string) $body[$snakeKey];
        }

        return null;
    }

    private function requireContext(): UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }

    private function requireOrganization(string $organizationUuid, UserContext $ctx): \WorkEddy\Modules\Organization\Domain\Organization
    {
        $organization = $this->requireOrganizations()->findByUuid($organizationUuid);
        if ($organization === null || ($ctx->organizationId !== null && $ctx->organizationId !== $organization->getId())) {
            throw new NotFoundException('Organization not found.');
        }

        return $organization;
    }

    private function serializeTask(\WorkEddy\Modules\Task\Domain\Task $task): array
    {
        if ($this->views !== null) {
            return $this->views->make($task);
        }

        return [
            'id' => $task->getUuid(),
            'organizationId' => (string) $task->getOrganizationId(),
            'worksiteId' => $task->getWorksiteId(),
            'departmentId' => $task->getDepartmentId(),
            'jobRoleId' => $task->getJobRoleId(),
            'name' => $task->getName(),
            'assessmentModel' => $task->getAssessmentModel(),
            'supportedInputTypes' => ['manual'],
            'supportsVideo' => false,
            'taskCode' => $task->getTaskCode(),
            'status' => $task->getStatus(),
            'description' => $task->getDescription(),
        ];
    }

    private function requireTasks(): ITaskRepository
    {
        return $this->tasks ?? throw new \RuntimeException('Task repository is not configured.');
    }

    private function requireOrganizations(): IOrganizationRepository
    {
        return $this->organizations ?? throw new \RuntimeException('Organization repository is not configured.');
    }

    private function requirePermissions(): IPermissionService
    {
        return $this->permissions ?? throw new \RuntimeException('Permission service is not configured.');
    }
}
