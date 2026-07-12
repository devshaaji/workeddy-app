<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain;

final class OrganizationMembership
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $uuid,
        private readonly int $userId,
        private readonly int $organizationId,
        private readonly ?string $organizationUuid,
        private int $roleId,
        private string $roleSlug,
        private readonly ?int $worksiteId = null,
        private readonly ?string $worksiteUuid = null,
        private readonly ?int $departmentId = null,
        private readonly ?string $departmentUuid = null,
        private readonly ?int $jobRoleId = null,
        private readonly ?string $jobRoleUuid = null,
        private readonly string $status = 'active',
        private readonly bool $isPrimary = true,
        private readonly ?string $organizationName = null,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getOrganizationId(): int
    {
        return $this->organizationId;
    }

    public function getOrganizationUuid(): ?string
    {
        return $this->organizationUuid;
    }

    public function getOrganizationName(): ?string
    {
        return $this->organizationName;
    }

    public function getRoleId(): int
    {
        return $this->roleId;
    }

    public function getRoleSlug(): string
    {
        return $this->roleSlug;
    }

    public function getWorksiteId(): ?int
    {
        return $this->worksiteId;
    }

    public function getDepartmentId(): ?int
    {
        return $this->departmentId;
    }

    public function getWorksiteUuid(): ?string
    {
        return $this->worksiteUuid;
    }

    public function getDepartmentUuid(): ?string
    {
        return $this->departmentUuid;
    }

    public function getJobRoleId(): ?int
    {
        return $this->jobRoleId;
    }

    public function getJobRoleUuid(): ?string
    {
        return $this->jobRoleUuid;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function assignRole(int $roleId, string $roleSlug): void
    {
        $this->roleId = $roleId;
        $this->roleSlug = $roleSlug;
    }
}
