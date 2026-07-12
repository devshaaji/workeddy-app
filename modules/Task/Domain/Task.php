<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Task\Domain;

final class Task
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $uuid,
        private readonly int $organizationId,
        private readonly ?int $worksiteId,
        private readonly ?int $departmentId,
        private readonly ?int $jobRoleId,
        private readonly string $name,
        private readonly string $assessmentModel = 'reba',
        private readonly ?string $taskCode = null,
        private readonly string $status = 'active',
        private readonly ?string $description = null,
        private readonly ?string $createdAt = null,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getOrganizationId(): int
    {
        return $this->organizationId;
    }

    public function getWorksiteId(): ?int
    {
        return $this->worksiteId;
    }

    public function getDepartmentId(): ?int
    {
        return $this->departmentId;
    }

    public function getJobRoleId(): ?int
    {
        return $this->jobRoleId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAssessmentModel(): string
    {
        return $this->assessmentModel;
    }

    public function getTaskCode(): ?string
    {
        return $this->taskCode;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
}
