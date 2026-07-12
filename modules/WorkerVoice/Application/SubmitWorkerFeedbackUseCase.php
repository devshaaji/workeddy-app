<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Application;

use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository;
use WorkEddy\Modules\WorkerVoice\Application\Services\WorkerFeedbackViewService;
use WorkEddy\Modules\WorkerVoice\Authorization\WorkerVoicePermissions;
use WorkEddy\Modules\WorkerVoice\Domain\Contracts\IWorkerVoiceRepository;
use WorkEddy\Modules\WorkerVoice\Domain\WorkerFeedback;
use WorkEddy\Modules\WorkerVoice\Settings\WorkerVoiceSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class SubmitWorkerFeedbackUseCase
{
    public function __construct(
        private readonly IWorkerVoiceRepository $feedback,
        private readonly ITaskRepository $tasks,
        private readonly IAssessmentRepository $assessments,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
        private readonly WorkerVoiceSettings $settings,
        private readonly WorkerFeedbackViewService $views,
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
            throw new ValidationException(['organization' => 'Worker feedback requires an organization-scoped user.']);
        }

        $taskUuid = $this->stringOrNull($input, 'taskUuid', 'task_uuid');
        $assessmentUuid = $this->stringOrNull($input, 'assessmentUuid', 'assessment_uuid');
        if ($this->settings->requireTaskOrAssessment() && $taskUuid === null && $assessmentUuid === null) {
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

        $bodyRegion = trim((string) ($input['bodyRegion'] ?? $input['body_region'] ?? ''));
        if (!in_array($bodyRegion, $this->settings->bodyRegionKeys(), true)) {
            throw new ValidationException(['bodyRegion' => 'Unsupported body region.']);
        }

        $suggestedChange = $this->stringOrNull($input, 'suggestedChange', 'suggested_change');
        if ($suggestedChange !== null && mb_strlen($suggestedChange) > $this->settings->maxSuggestedChangeLength()) {
            throw new ValidationException(['suggestedChange' => 'Suggested change is too long.']);
        }

        $worksiteId = $task?->getWorksiteId();
        $departmentId = $task?->getDepartmentId();
        $jobRoleId = $task?->getJobRoleId();
        $feedback = new WorkerFeedback(
            id: null,
            uuid: UuidSupport::generate(),
            organizationId: $organizationId,
            organizationUuid: $organizationUuid,
            taskId: $task?->getId(),
            taskUuid: $task?->getUuid(),
            assessmentUuid: $assessment?->getUuid(),
            worksiteId: $worksiteId,
            worksiteUuid: $worksiteId !== null ? $this->worksites?->findById($worksiteId)?->getUuid() : null,
            departmentId: $departmentId,
            departmentUuid: $departmentId !== null ? $this->departments?->findById($departmentId)?->getUuid() : null,
            jobRoleId: $jobRoleId,
            jobRoleUuid: $jobRoleId !== null ? $this->jobRoles?->findById($jobRoleId)?->getUuid() : null,
            submittedByUserId: $actor->userId,
            anonymousStatus: $this->boolValue($input['anonymousStatus'] ?? $input['anonymous_status'] ?? false),
            bodyRegion: $bodyRegion,
            hasDiscomfort: $this->boolValue($input['hasDiscomfort'] ?? $input['has_discomfort'] ?? true),
            discomfortLevel: $this->intValue($input, 'discomfortLevel', 'discomfort_level'),
            frequencyLevel: $this->intValue($input, 'frequencyLevel', 'frequency_level'),
            difficultyLevel: $this->intValue($input, 'difficultyLevel', 'difficulty_level'),
            reportingComfortLevel: $this->intValue($input, 'reportingComfortLevel', 'reporting_comfort_level'),
            pain7DayLevel: $this->intValue($input, 'pain7DayLevel', 'pain_7_day_level'),
            pain30DayLevel: $this->intValue($input, 'pain30DayLevel', 'pain_30_day_level'),
            suggestedChange: $suggestedChange,
            metadata: is_array($input['metadata'] ?? null) ? $input['metadata'] : [],
            createdAt: date('Y-m-d H:i:s'),
            updatedAt: date('Y-m-d H:i:s'),
        );

        $this->tx->transactional(fn() => $this->feedback->create($feedback));
        $view = $this->views->make($feedback, includeSensitiveIdentity: false);
        $this->audit->record('worker_feedback.submitted', 'worker_feedback', $feedback->uuid, beforeState: null, afterState: $view, actorId: (string) $actor->userId, actorType: 'user');

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

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 'true', 'yes', 'on'], true);
    }
}
