<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Domain;

use WorkEddy\Shared\Exceptions\ValidationException;

final class PilotSite
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $uuid,
        private readonly int $organizationId,
        private readonly string $organizationUuid,
        private readonly int $worksiteId,
        private readonly string $worksiteUuid,
        private readonly string $enrollmentDate,
        private readonly string $pilotStatus = 'enrolled',
        private readonly int $targetWorkerCount = 0,
        private readonly int $actualWorkerCount = 0,
        private readonly ?string $industry = null,
        private readonly ?string $notes = null,
        private readonly ?string $createdAt = null,
    ) {
        if ($this->targetWorkerCount < 0 || $this->actualWorkerCount < 0) {
            throw new ValidationException(['workerCount' => 'Worker counts cannot be negative.']);
        }
        if (!in_array($this->pilotStatus, ['enrolled', 'active', 'paused', 'completed'], true)) {
            throw new ValidationException(['pilotStatus' => 'Pilot status must be enrolled, active, paused, or completed.']);
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getUuid(): string { return $this->uuid; }
    public function getOrganizationId(): int { return $this->organizationId; }
    public function getOrganizationUuid(): string { return $this->organizationUuid; }
    public function getWorksiteId(): int { return $this->worksiteId; }
    public function getWorksiteUuid(): string { return $this->worksiteUuid; }
    public function getEnrollmentDate(): string { return $this->enrollmentDate; }
    public function getPilotStatus(): string { return $this->pilotStatus; }
    public function getTargetWorkerCount(): int { return $this->targetWorkerCount; }
    public function getActualWorkerCount(): int { return $this->actualWorkerCount; }
    public function getIndustry(): ?string { return $this->industry; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
}
