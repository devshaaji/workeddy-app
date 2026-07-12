<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Domain;

final class JobRole
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $uuid,
        private readonly int $organizationId,
        private readonly ?int $departmentId,
        private readonly string $name,
        private readonly string $status = 'active',
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

    public function getDepartmentId(): ?int
    {
        return $this->departmentId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
}
