<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Platform\Authorization\PermissionDefinition;
use WorkEddy\Platform\Module\ModuleRegistry;
use WorkEddy\Platform\Seeding\SeederInterface;
use WorkEddy\Shared\Support\UuidSupport;

return new class implements SeederInterface
{
    public function run(Connection $db): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $permissionIds = $this->syncPermissionCatalog($db, $now);
        $roleId = $this->resolveSuperAdminRoleId($db);

        foreach ($permissionIds as $permissionId) {
            $exists = $db->fetchOne(
                'SELECT 1 FROM iam_role_permissions WHERE role_id = ? AND permission_id = ?',
                [$roleId, $permissionId],
            );

            if ($exists !== false && $exists !== null) {
                continue;
            }

            $db->insert('iam_role_permissions', [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * Sync the current module permission catalog into IAM persistence.
     *
     * @return array<int, int> permission id list keyed by slug
     */
    private function syncPermissionCatalog(Connection $db, string $now): array
    {
        $modules = require dirname(__DIR__) . '/bootstrap/modules.php';
        $registry = new ModuleRegistry($modules);

        $definitions = [];
        foreach ($registry->permissionProviders() as $provider) {
            $definitions = array_merge($definitions, $provider->definitions());
        }
        foreach ($definitions as $definition) {
            if (!$definition instanceof PermissionDefinition) {
                continue;
            }

            $slug = PermissionDefinition::normalizeKey($definition->key);
            $existing = $db->fetchAssociative(
                'SELECT id, label, module, description, risk, system_only FROM iam_permissions WHERE permission_key = ?',
                [$slug],
            );

            $payload = [
                'label' => $definition->label,
                'module' => $definition->module,
                'description' => $definition->description,
                'risk' => $definition->risk,
                'system_only' => $definition->systemOnly ? 1 : 0,
                'updated_at' => $now,
            ];

            if ($existing === false) {
                $db->insert('iam_permissions', [
                    'uuid' => UuidSupport::generate(),
                    'permission_key' => $slug,
                    'module' => $definition->module,
                    'label' => $definition->label,
                    'description' => $definition->description,
                    'risk' => $definition->risk,
                    'system_only' => $definition->systemOnly ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                continue;
            }

            if (
                $existing['label'] !== $payload['label']
                || $existing['module'] !== $payload['module']
                || $existing['description'] !== $payload['description']
                || ($existing['risk'] ?? null) !== $payload['risk']
                || (int) ($existing['system_only'] ?? 0) !== $payload['system_only']
            ) {
                $db->update('iam_permissions', $payload, ['id' => (int) $existing['id']]);
            }
        }

        $permissionIds = [];
        foreach ($db->fetchAllAssociative('SELECT id, permission_key FROM iam_permissions') as $row) {
            $permissionIds[(string) $row['permission_key']] = (int) $row['id'];
        }

        return $permissionIds;
    }

    private function resolveSuperAdminRoleId(Connection $db): int
    {
        $roleId = $db->fetchOne("SELECT id FROM iam_roles WHERE name = 'super_admin'");
        if ($roleId === false || $roleId === null) {
            throw new RuntimeException('Super admin role was not seeded.');
        }

        return (int) $roleId;
    }
};
