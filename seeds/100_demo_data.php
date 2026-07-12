<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Platform\Seeding\SeederInterface;

return new class implements SeederInterface
{
    public function run(Connection $db): void
    {
        $now   = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $today = (new DateTimeImmutable())->format('Y-m-d');

        // Organization
        $orgUuid = '00000000-0000-4000-8000-000000000001';
        $orgId   = $this->upsertOrganization($db, $orgUuid, $now);

        // IAM role (super_admin)
        $roleId = $this->upsertRole($db, $now);

        // User
        $userUuid = '00000000-0000-4000-8000-000000000002';
        $userId   = $this->upsertUser($db, $userUuid, $roleId, $now);

        // Profile
        $this->upsertProfile($db, $userId, $now);

        // Membership
        $this->upsertMembership($db, $userId, $orgId, $roleId, $now);

        // Subscription
        $this->upsertSubscription($db, $orgId, $orgUuid, $today, $now);
    }

    private function upsertOrganization(Connection $db, string $uuid, string $now): int
    {
        $row = $db->fetchAssociative('SELECT id FROM organizations WHERE uuid = ?', [$uuid]);
        if ($row) {
            return (int) $row['id'];
        }

        $db->insert('organizations', [
            'uuid'       => $uuid,
            'name'       => 'Demo Warehouse',
            'slug'       => 'demo-warehouse',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $db->lastInsertId();
    }

    private function upsertRole(Connection $db, string $now): int
    {
        $row = $db->fetchAssociative("SELECT id FROM iam_roles WHERE name = 'super_admin'");
        if ($row) {
            return (int) $row['id'];
        }

        $db->insert('iam_roles', [
            'uuid'       => '00000000-0000-4000-8000-000000000010',
            'name'       => 'super_admin',
            'label'      => 'Super Admin',
            'scope'      => 'system',
            'is_system'  => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $db->lastInsertId();
    }

    private function upsertUser(Connection $db, string $uuid, int $roleId, string $now): int
    {
        $email = 'admin@demo.workeddy.com';
        $row   = $db->fetchAssociative('SELECT id FROM users WHERE email = ?', [$email]);
        if ($row) {
            return (int) $row['id'];
        }

        $db->insert('users', [
            'uuid'          => $uuid,
            'full_name'     => 'Super Admin',
            'email'         => $email,
            'password_hash' => password_hash('Password1!', PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'role_slug'     => 'super_admin',
            'status'        => 'active',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return (int) $db->lastInsertId();
    }

    private function upsertProfile(Connection $db, int $userId, string $now): void
    {
        $exists = $db->fetchOne('SELECT COUNT(*) FROM user_profiles WHERE user_id = ?', [$userId]);
        if ((int) $exists > 0) {
            return;
        }

        $db->insert('user_profiles', [
            'uuid'       => '00000000-0000-4000-8000-000000000003',
            'user_id'    => $userId,
            'full_name'  => 'Super Admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function upsertMembership(Connection $db, int $userId, int $orgId, int $roleId, string $now): void
    {
        $exists = $db->fetchOne(
            'SELECT COUNT(*) FROM organization_memberships WHERE user_id = ? AND organization_id = ?',
            [$userId, $orgId],
        );
        if ((int) $exists > 0) {
            return;
        }

        $db->insert('organization_memberships', [
            'uuid'            => '00000000-0000-4000-8000-000000000004',
            'user_id'         => $userId,
            'organization_id' => $orgId,
            'role_id'         => $roleId,
            'role_slug'       => 'super_admin',
            'status'          => 'active',
            'is_primary'      => 1,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    private function upsertSubscription(Connection $db, int $orgId, string $orgUuid, string $today, string $now): void
    {
        $exists = $db->fetchOne(
            'SELECT COUNT(*) FROM subscriptions WHERE organization_id = ?',
            [$orgId],
        );
        if ((int) $exists > 0) {
            return;
        }

        $db->insert('subscriptions', [
            'uuid'              => '00000000-0000-4000-8000-000000000005',
            'organization_id'   => $orgId,
            'organization_uuid' => $orgUuid,
            'plan_code'         => 'professional',
            'plan_name'         => 'Professional',
            'status'            => 'active',
            'billing_cycle'     => 'monthly',
            'start_date'        => $today,
            'activated_at'      => $now,
            'auto_renew'        => 1,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
    }
};
