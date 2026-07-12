<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Domain;

use WorkEddy\Shared\Exceptions\ValidationException;

final class CorrectiveAction
{
    public const STATUSES = ['open', 'assigned', 'in_progress', 'completed', 'verified', 'rejected', 'overdue'];

    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly int $organizationId,
        public readonly string $organizationUuid,
        public readonly string $assessmentUuid,
        public readonly ?string $recommendationUuid,
        public readonly ?string $libraryItemUuid,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $reason,
        public readonly string $controlType,
        public readonly string $hierarchyLevel,
        public readonly string $priority,
        public readonly string $status,
        public readonly ?int $assignedToUserId,
        public readonly ?int $assignedByUserId,
        public readonly ?string $dueDate,
        public readonly ?string $followUpAssessmentDueDate = null,
        public readonly array $evidenceRequirements = [],
        public readonly ?string $rejectReason = null,
        public readonly ?string $completedAt = null,
        public readonly ?string $verifiedAt = null,
        public readonly ?string $rejectedAt = null,
    ) {
        if (!in_array($status, self::STATUSES, true)) {
            throw new ValidationException(['status' => 'Invalid corrective action status.']);
        }
    }

    public function transition(string $nextStatus): self
    {
        $nextStatus = strtolower(trim($nextStatus));
        $allowed = [
            'open' => ['assigned', 'rejected', 'overdue'],
            'assigned' => ['in_progress', 'completed', 'rejected', 'overdue'],
            'in_progress' => ['completed', 'rejected', 'overdue'],
            'completed' => ['verified', 'rejected'],
            'verified' => [],
            'rejected' => [],
            'overdue' => ['in_progress', 'completed', 'rejected'],
        ][$this->status] ?? [];

        if (!in_array($nextStatus, $allowed, true)) {
            throw new ValidationException(['status' => "Cannot transition corrective action from {$this->status} to {$nextStatus}."]);
        }

        return new self(
            $this->id,
            $this->uuid,
            $this->organizationId,
            $this->organizationUuid,
            $this->assessmentUuid,
            $this->recommendationUuid,
            $this->libraryItemUuid,
            $this->title,
            $this->description,
            $this->reason,
            $this->controlType,
            $this->hierarchyLevel,
            $this->priority,
            $nextStatus,
            $this->assignedToUserId,
            $this->assignedByUserId,
            $this->dueDate,
            $this->followUpAssessmentDueDate,
            $this->evidenceRequirements,
            $this->rejectReason,
            $nextStatus === 'completed' ? date('Y-m-d H:i:s') : $this->completedAt,
            $nextStatus === 'verified' ? date('Y-m-d H:i:s') : $this->verifiedAt,
            $nextStatus === 'rejected' ? date('Y-m-d H:i:s') : $this->rejectedAt,
        );
    }

    public function withFollowUpDueDate(string $date): self
    {
        return new self($this->id, $this->uuid, $this->organizationId, $this->organizationUuid, $this->assessmentUuid, $this->recommendationUuid, $this->libraryItemUuid, $this->title, $this->description, $this->reason, $this->controlType, $this->hierarchyLevel, $this->priority, $this->status, $this->assignedToUserId, $this->assignedByUserId, $this->dueDate, $date, $this->evidenceRequirements, $this->rejectReason, $this->completedAt, $this->verifiedAt, $this->rejectedAt);
    }

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'uuid' => $this->uuid,
            'organizationUuid' => $this->organizationUuid,
            'assessmentUuid' => $this->assessmentUuid,
            'recommendationUuid' => $this->recommendationUuid,
            'libraryItemUuid' => $this->libraryItemUuid,
            'title' => $this->title,
            'description' => $this->description,
            'reason' => $this->reason,
            'controlType' => $this->controlType,
            'hierarchyLevel' => $this->hierarchyLevel,
            'priority' => $this->priority,
            'status' => $this->status,
            'assignedToUserId' => $this->assignedToUserId,
            'assignedByUserId' => $this->assignedByUserId,
            'dueDate' => $this->dueDate,
            'followUpAssessmentDueDate' => $this->followUpAssessmentDueDate,
            'evidenceRequirements' => $this->evidenceRequirements,
            'rejectReason' => $this->rejectReason,
            'completedAt' => $this->completedAt,
            'verifiedAt' => $this->verifiedAt,
            'rejectedAt' => $this->rejectedAt,
        ];
    }
}
