<?php

declare(strict_types=1);

namespace WorkEddy\Tests\IAM;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use WorkEddy\Platform\Schema\Modules\IAM\IamSchemaBuilder;

final class DefaultCustomerRolesSeedTest extends TestCase
{
    public function test_default_customer_roles_seed_is_idempotent_and_seeds_expected_permissions(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = new Schema();
        (new IamSchemaBuilder())->build($schema);

        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $seeder = require dirname(__DIR__, 2) . '/seeds/110_default_customer_roles.php';

        $seeder->run($connection);

        self::assertSame(5, (int) $connection->fetchOne('SELECT COUNT(*) FROM iam_roles'));
        self::assertGreaterThan(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM iam_permissions'));

        self::assertRoleHasPermissions($connection, 'organization_admin', [
            'organization.manage',
            'assessment.review',
            'corrective_action.manage_library',
            'reporting.settings',
            'ergonomics.score',
        ]);

        self::assertRoleHasPermissions($connection, 'safety_manager', [
            'organization.structure.manage',
            'assessment.comparison.generate',
            'corrective_action.verify',
            'reporting.view',
            'ergonomics.models.view',
        ]);

        self::assertRoleHasPermissions($connection, 'supervisor', [
            'task.create',
            'assessment.video.upload',
            'corrective_action.assign',
            'worker_voice.submit',
        ]);

        self::assertRoleHasPermissions($connection, 'worker', [
            'task.view',
            'assessment.video.upload',
            'privacy.consent.record',
            'worker_voice.submit',
        ]);

        self::assertRoleHasPermissions($connection, 'external_reviewer', [
            'assessment.review',
            'assessment.comparison.generate',
            'privacy.video.access',
            'worker_voice.aggregate.view',
            'ergonomics.models.view',
        ]);

        self::assertRoleLacksPermissions($connection, 'organization_admin', [
            'finance.view',
            'finance.manage',
            'finance.settings',
            'reporting.system.view',
        ]);

        $rolesBefore = (int) $connection->fetchOne('SELECT COUNT(*) FROM iam_roles');
        $permissionsBefore = (int) $connection->fetchOne('SELECT COUNT(*) FROM iam_permissions');
        $rolePermissionsBefore = (int) $connection->fetchOne('SELECT COUNT(*) FROM iam_role_permissions');

        $seeder->run($connection);

        self::assertSame($rolesBefore, (int) $connection->fetchOne('SELECT COUNT(*) FROM iam_roles'));
        self::assertSame($permissionsBefore, (int) $connection->fetchOne('SELECT COUNT(*) FROM iam_permissions'));
        self::assertSame($rolePermissionsBefore, (int) $connection->fetchOne('SELECT COUNT(*) FROM iam_role_permissions'));
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

    /**
     * @param string[] $unexpectedPermissions
     */
    private static function assertRoleLacksPermissions(\Doctrine\DBAL\Connection $connection, string $roleSlug, array $unexpectedPermissions): void
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

        foreach ($unexpectedPermissions as $permission) {
            self::assertNotContains($permission, $permissions, sprintf('Role "%s" should not receive permission "%s".', $roleSlug, $permission));
        }
    }
}
