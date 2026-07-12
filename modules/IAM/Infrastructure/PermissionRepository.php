<?php

/**
 * Permission DBAL repository.
 *
 * Owns: iam_permissions, iam_role_permissions tables.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Infrastructure;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Modules\IAM\Domain\Permission;
use WorkEddy\Platform\Authorization\PermissionDefinition;
use WorkEddy\Platform\Cache\ICacheService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;
use Doctrine\DBAL\Connection;

final class PermissionRepository implements IPermissionRepository
{
    private const CATALOG_TTL = 900;
    private const ROLE_PERMISSION_TTL = 300;

    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
        private readonly ?ICacheService $cache = null,
    ) {}

    public function findById(int|string $id): ?Permission
    {
        return $this->rememberCatalog('iam.permission.id.' . $id, function () use ($id): ?Permission {
            if (is_string($id) && !is_numeric($id)) {
                return $this->findByUuid($id);
            }

            $row = $this->connection->fetchAssociative(
                'SELECT * FROM iam_permissions WHERE id = ?',
                [(int) $id]
            );

            return $row ? $this->hydrate($row) : null;
        });
    }

    public function findByUuid(string $uuid): ?Permission
    {
        return $this->rememberCatalog('iam.permission.uuid.' . $uuid, function () use ($uuid): ?Permission {
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM iam_permissions WHERE uuid = ?',
                [$uuid]
            );

            return $row ? $this->hydrate($row) : null;
        });
    }

    public function findBySlug(string $slug): ?Permission
    {
        $slug = PermissionDefinition::normalizeKey($slug);

        return $this->rememberCatalog('iam.permission.slug.' . $slug, function () use ($slug): ?Permission {
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM iam_permissions WHERE permission_key = ?',
                [$slug]
            );

            return $row ? $this->hydrate($row) : null;
        });
    }

    public function findAll(): array
    {
        return $this->rememberCatalog('iam.permission.catalog.all', function (): array {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT * FROM iam_permissions ORDER BY module ASC, permission_key ASC'
            );
            return array_map(fn(array $row) => $this->hydrate($row), $rows);
        });
    }

    public function findByModule(string $module): array
    {
        $module = trim($module);

        return $this->rememberCatalog('iam.permission.catalog.module.' . $module, function () use ($module): array {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT * FROM iam_permissions WHERE module = ? ORDER BY permission_key ASC',
                [$module]
            );
            return array_map(fn(array $row) => $this->hydrate($row), $rows);
        });
    }

    public function resolveEffectivePermissions(int|string $userId, int|string $roleId): array
    {
        // Standarized: effective permissions are strictly role permissions
        $rolePerms = $this->rememberRolePermissions($roleId);
        sort($rolePerms);
        return $rolePerms;
    }

    public function listUserPermissionOverrides(int|string $userId): array
    {
        return [];
    }

    public function replaceUserPermissionOverrides(int|string $userId, array $grantPermissionIds, array $denyPermissionIds, int|string $actorId): void
    {
        // Overrides are deprecated under the unified schema standard
    }

    public function upsertCatalog(array $definitions): int
    {
        $affected = 0;
        $roleAssignmentsChanged = false;
        $nowStr = ($this->clock->now())->format('Y-m-d H:i:s');

        foreach ($definitions as $definition) {
            $slug = PermissionDefinition::normalizeKey($definition->key);

            $existing = $this->connection->fetchAssociative(
                'SELECT id, permission_key, label, module, description, risk, system_only
                 FROM iam_permissions
                 WHERE permission_key = ?',
                [$slug]
            );

            $payload = [
                'module' => $definition->module,
                'label' => $definition->label,
                'description' => $definition->description,
                'risk' => $definition->risk,
                'system_only' => $definition->systemOnly ? 1 : 0,
                'updated_at' => $nowStr,
            ];

            if ($existing === false) {
                $uuid = UuidSupport::generate();
                $this->connection->insert('iam_permissions', array_merge([
                    'uuid' => $uuid,
                    'permission_key' => $slug,
                    'created_at' => $nowStr,
                ], $payload));
                $permissionId = (int) $this->connection->lastInsertId();
                $affected++;
                $roleAssignmentsChanged = $this->syncDefaultRoleAssignments($permissionId, $definition) || $roleAssignmentsChanged;
                continue;
            }

            $permissionId = (int) $existing['id'];

            $changed =
                $existing['label'] !== $payload['label']
                || $existing['module'] !== $payload['module']
                || $existing['description'] !== $payload['description']
                || ($existing['risk'] ?? null) !== $payload['risk']
                || ((int)($existing['system_only'] ?? 0)) !== $payload['system_only'];

            if ($changed) {
                $this->connection->update('iam_permissions', $payload, ['id' => $permissionId]);
                $affected++;
            }

            $roleAssignmentsChanged = $this->syncDefaultRoleAssignments($permissionId, $definition) || $roleAssignmentsChanged;
        }

        if ($affected > 0 || $roleAssignmentsChanged) {
            $this->cache?->deleteByTag('iam.permission');
            $this->cache?->deleteByTag('iam.role');
        }

        return $affected;
    }

    private function rememberRolePermissions(int|string $roleId): array
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

        return $this->cache->get('iam.role.permissions.' . $roleId, $compute, self::ROLE_PERMISSION_TTL);
    }

    private function rememberCatalog(string $key, callable $compute): mixed
    {
        if ($this->cache === null) {
            return $compute();
        }

        return $this->cache->get($key, $compute, self::CATALOG_TTL);
    }

    private function syncDefaultRoleAssignments(int $permissionId, PermissionDefinition $definition): bool
    {
        $changed = false;

        foreach ($definition->defaultAssignments as $roleSlug) {
            $roleId = $this->connection->fetchOne('SELECT id FROM iam_roles WHERE name = ?', [$roleSlug]);
            if ($roleId === false || $roleId === null) {
                continue;
            }

            $exists = $this->connection->fetchOne(
                'SELECT 1 FROM iam_role_permissions WHERE role_id = ? AND permission_id = ?',
                [(int) $roleId, $permissionId]
            );

            if ($exists !== false && $exists !== null) {
                continue;
            }

            $this->connection->insert('iam_role_permissions', [
                'role_id' => (int) $roleId,
                'permission_id' => $permissionId,
                'created_at' => ($this->clock->now())->format('Y-m-d H:i:s'),
            ]);
            $changed = true;
        }

        return $changed;
    }

    private function hydrate(array $row): Permission
    {
        $defaultAssignments = [];

        return new Permission(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            slug: $row['permission_key'],
            name: $row['label'],
            module: $row['module'],
            description: $row['description'] ?? null,
            actionCategory: 'read',
            riskLevel: $row['risk'] ?? null,
            defaultAssignments: $defaultAssignments,
            systemOnly: (bool) ($row['system_only'] ?? false),
        );
    }
}
