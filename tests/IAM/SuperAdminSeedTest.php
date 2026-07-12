<?php

declare(strict_types=1);

namespace WorkEddy\Tests\IAM;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use WorkEddy\Platform\Schema\CanonicalSchemaBuilder;

final class SuperAdminSeedTest extends TestCase
{
    public function test_super_admin_seed_grants_full_permission_catalog(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = (new CanonicalSchemaBuilder())->buildAll();

        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $demoSeeder = require dirname(__DIR__, 2) . '/seeds/100_demo_data.php';
        $superAdminSeeder = require dirname(__DIR__, 2) . '/seeds/105_super_admin_permissions.php';

        $demoSeeder->run($connection);
        $superAdminSeeder->run($connection);

        $permissionCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM iam_permissions');
        $superAdminPermissionCount = (int) $connection->fetchOne(
            'SELECT COUNT(*)
             FROM iam_role_permissions rp
             JOIN iam_roles r ON r.id = rp.role_id
             WHERE r.name = ?',
            ['super_admin'],
        );

        self::assertGreaterThan(0, $permissionCount);
        self::assertSame($permissionCount, $superAdminPermissionCount);

        self::assertRoleHasPermissions($connection, 'super_admin', [
            'iam.user.view',
            'iam.role.manage',
            'iam.permission.assign',
            'iam.settings.manage',
            'billing.view_billing',
            'finance.view',
            'reporting.system.view',
            'notification.log.view',
            'organization.manage',
            'storage.settings.manage',
            'privacy.retention.manage',
        ]);
    }

    /**
     * @param string[] $expectedPermissions
     */
    private static function assertRoleHasPermissions(\Doctrine\DBAL\Connection $connection, string $roleSlug, array $expectedPermissions): void
    {
        $permissions = $connection->fetchFirstColumn(
            'SELECT p.permission_key
             FROM iam_role_permissions rp
             JOIN iam_roles r ON r.id = rp.role_id
             JOIN iam_permissions p ON p.id = rp.permission_id
             WHERE r.name = ?
             ORDER BY p.permission_key ASC',
            [$roleSlug],
        );

        foreach ($expectedPermissions as $permission) {
            self::assertContains($permission, $permissions, sprintf('Role "%s" is missing permission "%s".', $roleSlug, $permission));
        }
    }
}
