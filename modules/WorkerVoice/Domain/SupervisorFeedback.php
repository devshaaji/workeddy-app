<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Domain;

use WorkEddy\Shared\Exceptions\ValidationException;

final class SupervisorFeedback
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly int $organizationId,
        public readonly string $organizationUuid,
        public readonly ?int $taskId,
        public readonly ?string $taskUuid,
        public readonly ?string $assessmentUuid,
        public readonly ?int $worksiteId,
        public readonly ?string $worksiteUuid,
        public readonly ?int $departmentId,
        public readonly ?string $departmentUuid,
        public readonly ?int $jobRoleId,
        public readonly ?string $jobRoleUuid,
        public readonly int $submittedByUserId,
        public readonly ?string $bodyRegion,
        public readonly string $observedRiskLevel,
        public readonly string $observedIssueType,
        public readonly int $frequencyLevel,
        public readonly int $severityLevel,
        public readonly ?string $suggestedChange,
        public readonly ?string $notes,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
        if (trim($this->observedRiskLevel) === '' || trim($this->observedIssueType) === '') {
            throw new ValidationException(['observedRisk' => 'Observed risk level and issue type are required.']);
        }
        foreach (['frequencyLevel' => $this->frequencyLevel, 'severityLevel' => $this->severityLevel] as $field => $value) {
            if ($value < 0 || $value > 5) {
                throw new ValidationException([$field => 'Scale values must be between 0 and 5.']);
            }
        }
    }

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'uuid' => $this->uuid,
            'organizationUuid' => $this->organizationUuid,
            'taskUuid' => $this->taskUuid,
            'assessmentUuid' => $this->assessmentUuid,
            'worksiteUuid' => $this->worksiteUuid,
            'departmentUuid' => $this->departmentUuid,
            'jobRoleUuid' => $this->jobRoleUuid,
            'submittedByUserId' => $this->submittedByUserId,
            'bodyRegion' => $this->bodyRegion,
            'observedRiskLevel' => $this->observedRiskLevel,
            'observedIssueType' => $this->observedIssueType,
            'frequencyLevel' => $this->frequencyLevel,
            'severityLevel' => $this->severityLevel,
            'suggestedChange' => $this->suggestedChange,
            'notes' => $this->notes,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
