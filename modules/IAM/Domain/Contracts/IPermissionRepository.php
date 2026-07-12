<?php

/**
 * Permission repository interface — internal to IAM module.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain\Contracts;

use WorkEddy\Modules\IAM\Domain\Permission;
use WorkEddy\Platform\Authorization\PermissionDefinition;

interface IPermissionRepository
{
    public function findById(int|string $id): ?Permission;
    public function findByUuid(string $uuid): ?Permission;
    public function findBySlug(string $slug): ?Permission;

    /** @return Permission[] */
    public function findAll(): array;

    /** @return Permission[] */
    public function findByModule(string $module): array;

    /**
     * Resolve the effective permission slugs for a user:
     * (role permissions) + (user overrides granted) - (user overrides denied).
     *
     * @return string[]
     */
    public function resolveEffectivePermissions(int|string $userId, int|string $roleId): array;

    /** @return array */
    public function listUserPermissionOverrides(int|string $userId): array;

    /**
     * @param array $grantPermissionIds
     * @param array $denyPermissionIds
     */
    public function replaceUserPermissionOverrides(int|string $userId, array $grantPermissionIds, array $denyPermissionIds, int|string $actorId): void;

    /**
     * Upsert module-defined permission catalog into IAM persistence.
     *
     * @param PermissionDefinition[] $definitions
     * @return int Number of inserted/updated rows.
     */
    public function upsertCatalog(array $definitions): int;
}
