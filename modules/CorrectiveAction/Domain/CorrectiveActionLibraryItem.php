<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Domain;

final class CorrectiveActionLibraryItem
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $reason,
        public readonly string $controlType,
        public readonly string $hierarchyLevel,
        public readonly ?string $riskFactor,
        public readonly ?string $taskType,
        public readonly ?string $industry,
        public readonly string $priority,
        public readonly int $dueDays,
        public readonly bool $evidenceRequired,
        public readonly array $evidenceTypes = [],
        public readonly ?int $followUpDays = null,
        public readonly bool $isActive = true,
        public readonly ?string $updatedAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'reason' => $this->reason,
            'controlType' => $this->controlType,
            'hierarchyLevel' => $this->hierarchyLevel,
            'riskFactor' => $this->riskFactor,
            'taskType' => $this->taskType,
            'industry' => $this->industry,
            'priority' => $this->priority,
            'dueDays' => $this->dueDays,
            'evidenceRequired' => $this->evidenceRequired,
            'evidenceTypes' => $this->evidenceTypes,
            'followUpDays' => $this->followUpDays,
            'isActive' => $this->isActive,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
