<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Domain\Contracts;

use WorkEddy\Modules\Organization\Domain\Department;

interface IDepartmentRepository
{
    public function create(Department $department): int;
    public function update(Department $department): void;
    public function delete(string $uuid): void;
    public function findByUuid(string $uuid): ?Department;
    public function findById(int $id): ?Department;

    /**
     * @return list<Department>
     */
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array;
}
