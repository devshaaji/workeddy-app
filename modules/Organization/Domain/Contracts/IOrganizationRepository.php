<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Domain\Contracts;

use WorkEddy\Modules\Organization\Domain\Organization;

interface IOrganizationRepository
{
    public function create(Organization $organization): int;
    public function update(Organization $organization): void;

    /**
     * Soft-deletes an organization (sets deleted_at, status='deleted').
     * All find* methods already filter out soft-deleted rows.
     */
    public function softDelete(string $uuid): void;

    public function findById(int $id): ?Organization;
    public function findByUuid(string $uuid): ?Organization;
    public function findBySlug(string $slug): ?Organization;

    /**
     * @return list<Organization>
     */
    public function findAll(int $limit = 50, int $offset = 0): array;
}
