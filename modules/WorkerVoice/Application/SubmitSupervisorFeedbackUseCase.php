<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Application;

use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository;
use WorkEddy\Modules\WorkerVoice\Authorization\WorkerVoicePermissions;
use WorkEddy\Modules\WorkerVoice\Domain\Contracts\ISupervisorFeedbackRepository;
use WorkEddy\Modules\WorkerVoice\Domain\SupervisorFeedback;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class SubmitSupervisorFeedbackUseCase
{
    public function __construct(
        private readonly ISupervisorFeedbackRepository $feedback,
        private readonly ITaskRepository $tasks,
        private readonly IAssessmentRepository $assessments,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
        private readonly ?IWorksiteRepository $worksites = null,
        private readonly ?IDepartmentRepository $departments = null,
        private readonly ?IJobRoleRepository $jobRoles = null,
    ) {}

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function execute(array $input, UserContext $actor): array
    {
        $this->permissions->requirePrivilege($actor, WorkerVoicePermissions::SUBMIT);

        $organizationId = $actor->organizationId ?? 0;
        $organizationUuid = $actor->organizationUuid ?? '';
        if ($organizationId <= 0 || $organizationUuid === '') {
            throw new ValidationException(['organization' => 'Supervisor feedback requires an organization-scoped user.']);
        }

        $taskUuid = $this->stringOrNull($input, 'taskUuid', 'task_uuid');
        $assessmentUuid = $this->stringOrNull($input, 'assessmentUuid', 'assessment_uuid');
        if ($taskUuid === null && $assessmentUuid === null) {
            throw new ValidationException(['taskUuid' => 'Task or assessment link is required.']);
        }

        $assessment = null;
        if ($assessmentUuid !== null) {
            $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'));
            if ($assessment === null || $assessment->getOrganizationId() !== $organizationId) {
                throw new NotFoundException('Assessment not found.');
            }
            if ($taskUuid !== null && $taskUuid !== $assessment->getTaskUuid()) {
                throw new ValidationException(['taskUuid' => 'Task must match linked assessment task.']);
            }
            $taskUuid = $assessment->getTaskUuid();
        }

        $task = null;
        if ($taskUuid !== null) {
            $task = $this->tasks->findByUuid(UuidSupport::requireValid($taskUuid, 'taskUuid'));
            if ($task === null || $task->getOrganizationId() !== $organizationId) {
                throw new NotFoundException('Task not found.');
            }
        }

        $feedback = new SupervisorFeedback(
            id: null,
            uuid: UuidSupport::generate(),
            organizationId: $organizationId,
            organizationUuid: $organizationUuid,
            taskId: $task?->getId(),
            taskUuid: $task?->getUuid(),
            assessmentUuid: $assessment?->getUuid(),
            worksiteId: $task?->getWorksiteId(),
            worksiteUuid: $task?->getWorksiteId() !== null ? $this->worksites?->findById($task->getWorksiteId())?->getUuid() : null,
            departmentId: $task?->getDepartmentId(),
            departmentUuid: $task?->getDepartmentId() !== null ? $this->departments?->findById($task->getDepartmentId())?->getUuid() : null,
            jobRoleId: $task?->getJobRoleId(),
            jobRoleUuid: $task?->getJobRoleId() !== null ? $this->jobRoles?->findById($task->getJobRoleId())?->getUuid() : null,
            submittedByUserId: (int) $actor->userId,
            bodyRegion: $this->stringOrNull($input, 'bodyRegion', 'body_region'),
            observedRiskLevel: trim((string) ($input['observedRiskLevel'] ?? $input['observed_risk_level'] ?? '')),
            observedIssueType: trim((string) ($input['observedIssueType'] ?? $input['observed_issue_type'] ?? '')),
            frequencyLevel: $this->intValue($input, 'frequencyLevel', 'frequency_level'),
            severityLevel: $this->intValue($input, 'severityLevel', 'severity_level'),
            suggestedChange: $this->stringOrNull($input, 'suggestedChange', 'suggested_change'),
            notes: $this->stringOrNull($input, 'notes', 'notes'),
            createdAt: date('Y-m-d H:i:s'),
            updatedAt: date('Y-m-d H:i:s'),
        );

        $this->tx->transactional(fn() => $this->feedback->create($feedback));
        $view = $feedback->toView();
        $this->audit->record('supervisor_feedback.submitted', 'supervisor_feedback', $feedback->uuid, beforeState: null, afterState: $view, actorId: (string) $actor->userId, actorType: 'user');

        return $view;
    }

    /** @param array<string, mixed> $input */
    private function intValue(array $input, string $camel, string $snake): int
    {
        return (int) ($input[$camel] ?? $input[$snake] ?? 0);
    }

    /** @param array<string, mixed> $input */
    private function stringOrNull(array $input, string $camel, string $snake): ?string
    {
        $value = $input[$camel] ?? $input[$snake] ?? null;
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
