<?php

/**
 * User repository interface — internal to IAM module.
 *
 * DBAL-backed. Owns ONLY IAM tables (users, roles, permissions, etc.).
 * Never exposed outside the module — other modules use IAuthService/IUserContextService contracts.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain\Contracts;

use WorkEddy\Modules\IAM\Domain\User;

interface IUserRepository
{
    /** Persist a new user. Returns the generated user ID. */
    public function create(User $user): int|string;

    /** Update a user's mutable fields (profile, status, password, role, last_login). */
    public function update(User $user): void;

    /** Find by primary key. */
    public function findById(int|string $id): ?User;
    public function findByUuid(string $uuid): ?User;

    /** Find by email (for uniqueness checks). Case-insensitive. */
    public function findByEmail(string $email): ?User;

    /**
     * List users with optional filters.
     *
     * @param array{status?: string, role_slug?: string, lga_code?: string, search?: string, organization_uuid?: string} $filters
     * @return User[]
     */
    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array;

    /** Count users matching filters. */
    public function count(array $filters = []): int;

    /**
     * Count users grouped by role ID.
     *
     * @param array $roleIds
     * @return array<int|string, int>
     */
    public function countByRoleIds(array $roleIds): array;

    /**
     * List users assigned to a role.
     *
     * @return User[]
     */
    public function findByRoleId(int|string $roleId, int $limit = 25): array;

    public function bumpAuthzVersion(int|string $userId): int;
}
