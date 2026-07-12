<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Platform\Authorization\PermissionDefinition;
use WorkEddy\Platform\Module\ModuleRegistry;
use WorkEddy\Platform\Seeding\SeederInterface;
use WorkEddy\Shared\Support\UuidSupport;

return new class implements SeederInterface
{
    /**
     * Canonical customer baseline roles.
     *
     * @var array<string, array{label:string, description:string, permissions: string[]}>
     */
    private const ROLES = [
        'organization_admin' => [
            'label' => 'Organization Admin',
            'description' => 'Full organization owner with administrative access to the prevention workflow.',
            'permissions' => [
                'organization.view',
                'organization.manage',
                'organization.members.manage',
                'organization.structure.manage',
                'task.view',
                'task.create',
                'task.update',
                'assessment.view',
                'assessment.create',
                'assessment.update',
                'assessment.review',
                'assessment.lock',
                'assessment.video.upload',
                'assessment.comparison.view',
                'assessment.comparison.generate',
                'assessment.comparison.lock',
                'corrective_action.view',
                'corrective_action.generate_recommendations',
                'corrective_action.review_recommendations',
                'corrective_action.assign',
                'corrective_action.update_status',
                'corrective_action.upload_evidence',
                'corrective_action.verify',
                'corrective_action.manage_library',
                'privacy.consent.record',
                'privacy.video.access',
                'privacy.retention.manage',
                'privacy.retention.enforce',
                'privacy.audit.view',
                'worker_voice.submit',
                'worker_voice.view',
                'worker_voice.view_sensitive',
                'worker_voice.aggregate.view',
                'worker_voice.export',
                'export.research.view',
                'export.research.preview',
                'export.research.generate',
                'export.research.download',
                'reporting.view',
                'reporting.settings',
                'audit.view',
                'audit.export',
                'audit.settings.manage',
                'ergonomics.score',
                'ergonomics.models.view',
            ],
        ],
        'safety_manager' => [
            'label' => 'Safety Manager',
            'description' => 'Operational safety lead for assessments, corrective actions, and validation.',
            'permissions' => [
                'organization.view',
                'organization.members.manage',
                'organization.structure.manage',
                'task.view',
                'task.create',
                'task.update',
                'assessment.view',
                'assessment.create',
                'assessment.update',
                'assessment.review',
                'assessment.lock',
                'assessment.video.upload',
                'assessment.comparison.view',
                'assessment.comparison.generate',
                'assessment.comparison.lock',
                'corrective_action.view',
                'corrective_action.generate_recommendations',
                'corrective_action.review_recommendations',
                'corrective_action.assign',
                'corrective_action.update_status',
                'corrective_action.upload_evidence',
                'corrective_action.verify',
                'corrective_action.manage_library',
                'privacy.consent.record',
                'privacy.video.access',
                'privacy.retention.enforce',
                'privacy.audit.view',
                'worker_voice.submit',
                'worker_voice.view',
                'worker_voice.view_sensitive',
                'worker_voice.aggregate.view',
                'worker_voice.export',
                'export.research.view',
                'export.research.preview',
                'export.research.generate',
                'export.research.download',
                'reporting.view',
                'audit.view',
                'audit.export',
                'ergonomics.score',
                'ergonomics.models.view',
            ],
        ],
        'supervisor' => [
            'label' => 'Supervisor',
            'description' => 'Frontline lead for task evidence, assigned corrective actions, and completion follow-up.',
            'permissions' => [
                'organization.view',
                'task.view',
                'task.create',
                'task.update',
                'assessment.view',
                'assessment.create',
                'assessment.update',
                'assessment.video.upload',
                'assessment.comparison.view',
                'corrective_action.view',
                'corrective_action.assign',
                'corrective_action.update_status',
                'corrective_action.upload_evidence',
                'privacy.consent.record',
                'worker_voice.submit',
                'worker_voice.view',
                'worker_voice.aggregate.view',
            ],
        ],
        'worker' => [
            'label' => 'Worker',
            'description' => 'Task participant who submits evidence, feedback, and consented uploads.',
            'permissions' => [
                'organization.view',
                'task.view',
                'assessment.view',
                'assessment.video.upload',
                'privacy.consent.record',
                'worker_voice.submit',
                'worker_voice.view',
            ],
        ],
        'external_reviewer' => [
            'label' => 'External Reviewer',
            'description' => 'Independent ergonomist who validates assessments and final reports.',
            'permissions' => [
                'organization.view',
                'task.view',
                'assessment.view',
                'assessment.review',
                'assessment.lock',
                'assessment.comparison.view',
                'assessment.comparison.generate',
                'assessment.comparison.lock',
                'corrective_action.view',
                'corrective_action.verify',
                'privacy.video.access',
                'worker_voice.view',
                'worker_voice.aggregate.view',
                'ergonomics.models.view',
            ],
        ],
    ];

    public function run(Connection $db): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $permissionIds = $this->syncPermissionCatalog($db, $now);
        $roleIds = $this->upsertRoles($db, $now);
        $this->syncRolePermissions($db, $roleIds, $permissionIds, $now);
    }

    /**
     * Sync the current module permission catalog into IAM persistence.
     *
     * @return array<string, int> permission slug => permission id
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

    /**
     * @return array<string, int> role slug => role id
     */
    private function upsertRoles(Connection $db, string $now): array
    {
        $roleIds = [];

        foreach (self::ROLES as $slug => $definition) {
            $existing = $db->fetchAssociative('SELECT id, label, scope, is_system FROM iam_roles WHERE name = ?', [$slug]);
            $payload = [
                'label' => $definition['label'],
                'scope' => 'customer',
                'is_system' => 0,
                'updated_at' => $now,
            ];

            if ($existing === false) {
                $db->insert('iam_roles', [
                    'uuid' => UuidSupport::generate(),
                    'name' => $slug,
                    'label' => $definition['label'],
                    'scope' => 'customer',
                    'is_system' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $roleIds[$slug] = (int) $db->lastInsertId();
                continue;
            }

            $db->update('iam_roles', $payload, ['id' => (int) $existing['id']]);
            $roleIds[$slug] = (int) $existing['id'];
        }

        return $roleIds;
    }

    /**
     * @param array<string, int> $roleIds
     * @param array<string, int> $permissionIds
     */
    private function syncRolePermissions(Connection $db, array $roleIds, array $permissionIds, string $now): void
    {
        foreach (self::ROLES as $slug => $definition) {
            $roleId = $roleIds[$slug] ?? null;
            if ($roleId === null) {
                throw new RuntimeException(sprintf('Role "%s" was not seeded.', $slug));
            }

            foreach ($definition['permissions'] as $permissionSlug) {
                $permissionId = $permissionIds[$permissionSlug] ?? null;
                if ($permissionId === null) {
                    throw new RuntimeException(sprintf('Permission "%s" was not found for role "%s".', $permissionSlug, $slug));
                }

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
    }
};
