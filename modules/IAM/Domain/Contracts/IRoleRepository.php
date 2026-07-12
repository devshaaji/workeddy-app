<?php

/**
 * Role repository interface — internal to IAM module.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain\Contracts;

use WorkEddy\Modules\IAM\Domain\Role;

interface IRoleRepository
{
    public function findById(int|string $id): ?Role;
    public function findByUuid(string $uuid): ?Role;
    public function findBySlug(string $slug): ?Role;

    /** @return Role[] */
    public function findAll(): array;

    public function create(Role $role): int|string;
    public function update(Role $role): void;

    /**
     * Get all permission slugs for a given role ID.
     *
     * @return string[]
     */
    public function getPermissionsForRole(int|string $roleId): array;

    /**
     * Set the permission assignments for a role (replace all).
     *
     * @param int|string $roleId
     * @param array $permissionIds
     */
    public function syncPermissions(int|string $roleId, array $permissionIds): void;
}
