<?php

/**
 * Role DBAL repository.
 *
 * Owns: iam_roles, iam_role_permissions tables.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Infrastructure;

use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Role;
use WorkEddy\Platform\Cache\ICacheService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;
use Doctrine\DBAL\Connection;

final class RoleRepository implements IRoleRepository
{
    private const ROLE_TTL = 300;

    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
        private readonly ?ICacheService $cache = null,
    ) {}

    public function findById(int|string $id): ?Role
    {
        if (is_string($id) && !is_numeric($id)) {
            return $this->findByUuid($id);
        }

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM iam_roles WHERE id = ?',
            [(int) $id]
        );

        if (!$row) {
            return null;
        }

        $permissions = $this->getPermissionsForRole((int) $id);
        return $this->hydrate($row, $permissions);
    }

    public function findByUuid(string $uuid): ?Role
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM iam_roles WHERE uuid = ?',
            [$uuid]
        );

        if (!$row) {
            return null;
        }

        $permissions = $this->getPermissionsForRole((int) $row['id']);
        return $this->hydrate($row, $permissions);
    }

    public function findBySlug(string $slug): ?Role
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM iam_roles WHERE name = ?',
            [$slug]
        );

        if (!$row) {
            return null;
        }

        $permissions = $this->getPermissionsForRole((int) $row['id']);
        return $this->hydrate($row, $permissions);
    }

    /**
     * @return Role[]
     */
    public function findAll(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM iam_roles ORDER BY label ASC'
        );

        return array_map(function (array $row) {
            $permissions = $this->getPermissionsForRole((int) $row['id']);
            return $this->hydrate($row, $permissions);
        }, $rows);
    }

    public function create(Role $role): int|string
    {
        $nowStr = ($this->clock->now())->format('Y-m-d H:i:s');
        $uuid = $role->getUuid() ?: UuidSupport::generate();

        $this->connection->insert('iam_roles', [
            'uuid'       => $uuid,
            'name'       => $role->getSlug(),
            'label'      => $role->getName(),
            'scope'      => $role->getScope(),
            'is_system'  => $role->isSystem() ? 1 : 0,
            'created_at' => $nowStr,
            'updated_at' => $nowStr,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(Role $role): void
    {
        $nowStr = ($this->clock->now())->format('Y-m-d H:i:s');

        $this->connection->update('iam_roles', [
            'label'      => $role->getName(),
            'scope'      => $role->getScope(),
            'updated_at' => $nowStr,
        ], ['id' => (int) $role->getId()]);
    }

    /**
     * Get all permission slugs for a given role ID.
     *
     * @return string[]
     */
    public function getPermissionsForRole(int|string $roleId): array
    {
        $compute = fn(): array => $this->connection->fetchFirstColumn(
            'SELECT p.permission_key
             FROM iam_role_permissions rp
             JOIN iam_permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ?',
            [(int) $roleId]
        );

        if ($this->cache === null) {
            return $compute();
        }

        return $this->cache->get('iam.role.permissions.' . $roleId, $compute, self::ROLE_TTL);
    }

    /**
     * Set the permission assignments for a role (replace all).
     *
     * @param int|string $roleId
     * @param array $permissionIds array of integer permission IDs
     */
    public function syncPermissions(int|string $roleId, array $permissionIds): void
    {
        $roleIdInt = (int) $roleId;
        $this->connection->delete('iam_role_permissions', ['role_id' => $roleIdInt]);

        foreach ($permissionIds as $permId) {
            $this->connection->insert('iam_role_permissions', [
                'role_id'       => $roleIdInt,
                'permission_id' => (int) $permId,
                'created_at'    => ($this->clock->now())->format('Y-m-d H:i:s'),
            ]);
        }

        $this->cache?->delete('iam.role.permissions.' . $roleId);
    }

    /**
     * @param string[] $permissions
     */
    private function hydrate(array $row, array $permissions): Role
    {
        return new Role(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            slug: $row['name'],
            name: $row['label'],
            description: null,
            isSystem: (bool) $row['is_system'],
            scope: (string) ($row['scope'] ?? 'staff'),
            permissions: $permissions,
        );
    }
}
