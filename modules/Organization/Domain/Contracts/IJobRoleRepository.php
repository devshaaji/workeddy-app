<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Domain\Contracts;

use WorkEddy\Modules\Organization\Domain\JobRole;

interface IJobRoleRepository
{
    public function create(JobRole $jobRole): int;
    public function update(JobRole $jobRole): void;
    public function delete(string $uuid): void;
    public function findByUuid(string $uuid): ?JobRole;
    public function findById(int $id): ?JobRole;

    /**
     * @return list<JobRole>
     */
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array;
}
