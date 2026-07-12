<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Domain;

final class CorrectiveActionRecommendation
{
    /** @param array<string, mixed> $evidence */
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly int $organizationId,
        public readonly string $organizationUuid,
        public readonly string $assessmentUuid,
        public readonly ?string $libraryItemUuid,
        public readonly string $controlCode,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $reason,
        public readonly string $hierarchyLevel,
        public readonly string $controlType,
        public readonly string $priority,
        public readonly int $rankOrder,
        public readonly float $expectedRiskReductionPct,
        public readonly ?int $dueDays,
        public readonly ?int $followUpDays,
        public readonly string $status,
        public readonly array $evidence = [],
        public readonly ?string $rejectReason = null,
        public readonly ?string $reviewedAt = null,
        public readonly ?int $reviewedBy = null,
    ) {}

    public function accepted(int $actorId): self
    {
        return new self($this->id, $this->uuid, $this->organizationId, $this->organizationUuid, $this->assessmentUuid, $this->libraryItemUuid, $this->controlCode, $this->title, $this->description, $this->reason, $this->hierarchyLevel, $this->controlType, $this->priority, $this->rankOrder, $this->expectedRiskReductionPct, $this->dueDays, $this->followUpDays, 'accepted', $this->evidence, null, date('Y-m-d H:i:s'), $actorId);
    }

    public function rejected(int $actorId, ?string $rejectReason = null): self
    {
        return new self($this->id, $this->uuid, $this->organizationId, $this->organizationUuid, $this->assessmentUuid, $this->libraryItemUuid, $this->controlCode, $this->title, $this->description, $this->reason, $this->hierarchyLevel, $this->controlType, $this->priority, $this->rankOrder, $this->expectedRiskReductionPct, $this->dueDays, $this->followUpDays, 'rejected', $this->evidence, $rejectReason, date('Y-m-d H:i:s'), $actorId);
    }

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'uuid' => $this->uuid,
            'organizationUuid' => $this->organizationUuid,
            'assessmentUuid' => $this->assessmentUuid,
            'libraryItemUuid' => $this->libraryItemUuid,
            'controlCode' => $this->controlCode,
            'title' => $this->title,
            'description' => $this->description,
            'reason' => $this->reason,
            'hierarchyLevel' => $this->hierarchyLevel,
            'controlType' => $this->controlType,
            'priority' => $this->priority,
            'rankOrder' => $this->rankOrder,
            'expectedRiskReductionPct' => $this->expectedRiskReductionPct,
            'dueDays' => $this->dueDays,
            'followUpDays' => $this->followUpDays,
            'status' => $this->status,
            'evidence' => $this->evidence,
            'rejectReason' => $this->rejectReason,
            'reviewedAt' => $this->reviewedAt,
            'reviewedBy' => $this->reviewedBy,
        ];
    }
}
