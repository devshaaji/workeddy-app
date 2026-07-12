<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Domain;

use WorkEddy\Shared\Exceptions\ValidationException;

final class WorkerFeedback
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
        public readonly ?int $submittedByUserId,
        public readonly bool $anonymousStatus,
        public readonly string $bodyRegion,
        public readonly bool $hasDiscomfort,
        public readonly int $discomfortLevel,
        public readonly int $frequencyLevel,
        public readonly int $difficultyLevel,
        public readonly int $reportingComfortLevel,
        public readonly int $pain7DayLevel,
        public readonly int $pain30DayLevel,
        public readonly ?string $suggestedChange,
        public readonly array $metadata = [],
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
        if (trim($bodyRegion) === '') {
            throw new ValidationException(['bodyRegion' => 'Body region is required.']);
        }

        foreach ([
            'discomfortLevel' => $discomfortLevel,
            'frequencyLevel' => $frequencyLevel,
            'difficultyLevel' => $difficultyLevel,
            'reportingComfortLevel' => $reportingComfortLevel,
            'pain7DayLevel' => $pain7DayLevel,
            'pain30DayLevel' => $pain30DayLevel,
        ] as $field => $value) {
            if ($value < 0 || $value > 5) {
                throw new ValidationException([$field => 'Scale values must be between 0 and 5.']);
            }
        }
    }

    public function isAnonymous(): bool
    {
        return $this->anonymousStatus;
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
            'anonymousStatus' => $this->anonymousStatus,
            'bodyRegion' => $this->bodyRegion,
            'hasDiscomfort' => $this->hasDiscomfort,
            'discomfortLevel' => $this->discomfortLevel,
            'frequencyLevel' => $this->frequencyLevel,
            'difficultyLevel' => $this->difficultyLevel,
            'reportingComfortLevel' => $this->reportingComfortLevel,
            'pain7DayLevel' => $this->pain7DayLevel,
            'pain30DayLevel' => $this->pain30DayLevel,
            'suggestedChange' => $this->suggestedChange,
            'metadata' => $this->metadata,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
