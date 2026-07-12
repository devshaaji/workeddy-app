<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\IAM;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

final class IamSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'iam';
    }

    public function tables(): array
    {
        return [
            'users',
            'iam_roles',
            'iam_permissions',
            'iam_role_permissions',
            'iam_role_department_scopes',
            'iam_auth_tokens',
            'iam_user_sessions',
            'iam_password_resets',
            'iam_otp_challenges',
            'iam_deliveries',
            'iam_service_credentials',
            'iam_user_sources',
        ];
    }

    public function build(Schema $schema): void
    {
        $this->createUsersTable($schema);
        $this->createRoles($schema);
        $this->createPermissions($schema);
        $this->createRolePermissions($schema);
        $this->createRoleDepartmentScopes($schema);
        $this->createAuthTokens($schema);
        $this->createUserSessions($schema);
        $this->createPasswordResets($schema);
        $this->createOtpChallenges($schema);
        $this->createDeliveries($schema);
        $this->createServiceCredentials($schema);
        $this->createUserSources($schema);
    }

    private function createUsersTable(Schema $schema): void
    {
        if ($schema->hasTable('users')) {
            return;
        }

        $table = $schema->createTable('users');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('full_name', 'string', ['length' => 240]);
        $table->addColumn('email', 'string', ['length' => 240]);
        $table->addColumn('employee_id', 'string', ['length' => 80, 'notnull' => false]);
        $table->addColumn('phone', 'string', ['length' => 40, 'notnull' => false]);
        $table->addColumn('password_hash', 'string', ['length' => 255]);
        $table->addColumn('role_id', 'integer', ['default' => 0]);
        $table->addColumn('role_slug', 'string', ['length' => 120, 'default' => 'operator']);
        $table->addColumn('totp_secret_encrypted', 'text', ['notnull' => false]);
        $table->addColumn('recovery_codes_json', 'json', ['notnull' => false]);
        $table->addColumn('otp_enabled', 'boolean', ['default' => false]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'active']);
        $table->addColumn('last_login_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('authz_version', 'integer', ['default' => 1]);
        $this->timestamps($table);
        $this->softDeletes($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'users_uuid_unique');
        $table->addUniqueIndex(['email'], 'users_email_unique');
        $table->addUniqueIndex(['employee_id'], 'users_employee_id_unique');
        $table->addIndex(['status'], 'users_status_idx');
        $table->addIndex(['otp_enabled'], 'users_otp_enabled_idx');
    }

    private function createRoles(Schema $schema): void
    {
        if ($schema->hasTable('iam_roles')) {
            return;
        }

        $table = $schema->createTable('iam_roles');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('name', 'string', ['length' => 120]);
        $table->addColumn('label', 'string', ['length' => 180]);
        $table->addColumn('scope', 'string', ['length' => 40, 'default' => 'staff']);
        $table->addColumn('is_system', 'boolean', ['default' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'iam_roles_uuid_unique');
        $table->addUniqueIndex(['name'], 'iam_roles_name_unique');
        $table->addIndex(['scope'], 'iam_roles_scope_idx');
    }

    private function createPermissions(Schema $schema): void
    {
        if ($schema->hasTable('iam_permissions')) {
            return;
        }

        $table = $schema->createTable('iam_permissions');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('permission_key', 'string', ['length' => 180]);
        $table->addColumn('module', 'string', ['length' => 80]);
        $table->addColumn('label', 'string', ['length' => 180]);
        $table->addColumn('description', 'text');
        $table->addColumn('risk', 'string', ['length' => 40, 'notnull' => false]);
        $table->addColumn('system_only', 'boolean', ['default' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'iam_permissions_uuid_unique');
        $table->addUniqueIndex(['permission_key'], 'iam_permissions_key_unique');
        $table->addIndex(['module'], 'iam_permissions_module_idx');
    }

    private function createRolePermissions(Schema $schema): void
    {
        if ($schema->hasTable('iam_role_permissions')) {
            return;
        }

        $table = $schema->createTable('iam_role_permissions');
        $table->addColumn('role_id', 'integer');
        $table->addColumn('permission_id', 'integer');
        $this->createdAt($table);
        $table->setPrimaryKey(['role_id', 'permission_id']);
        $table->addForeignKeyConstraint('iam_roles', ['role_id'], ['id'], ['onDelete' => 'CASCADE'], 'iam_role_permissions_role_fk');
        $table->addForeignKeyConstraint('iam_permissions', ['permission_id'], ['id'], ['onDelete' => 'CASCADE'], 'iam_role_permissions_permission_fk');
    }

    private function createRoleDepartmentScopes(Schema $schema): void
    {
        if ($schema->hasTable('iam_role_department_scopes')) {
            return;
        }

        $table = $schema->createTable('iam_role_department_scopes');
        $table->addColumn('role_slug', 'string', ['length' => 120]);
        $table->addColumn('permission_key', 'string', ['length' => 180]);
        $table->addColumn('scope_mode', 'string', ['length' => 40, 'default' => 'self']);
        $this->createdAt($table);
        $table->setPrimaryKey(['role_slug', 'permission_key']);
        $table->addIndex(['permission_key'], 'iam_role_department_scopes_permission_idx');
        $table->addIndex(['scope_mode'], 'iam_role_department_scopes_scope_idx');
    }

    private function createAuthTokens(Schema $schema): void
    {
        if ($schema->hasTable('iam_auth_tokens')) {
            return;
        }

        $table = $schema->createTable('iam_auth_tokens');
        $this->uuid($table, 'token_id');
        $table->addColumn('principal_id', 'string', ['length' => 120]);
        $table->addColumn('principal_type', 'string', ['length' => 60]);
        $this->uuid($table, 'outlet_id', false);
        $table->addColumn('token_hash', 'string', ['length' => 128]);
        $table->addColumn('name', 'string', ['length' => 160]);
        $table->addColumn('permissions_json', 'json', ['notnull' => false]);
        $table->addColumn('last_used_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('expires_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('revoked_at', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['token_id']);
        $table->addUniqueIndex(['token_hash'], 'iam_auth_tokens_hash_unique');
        $table->addIndex(['principal_id', 'principal_type', 'revoked_at'], 'iam_auth_tokens_principal_idx');
    }

    private function createUserSessions(Schema $schema): void
    {
        if ($schema->hasTable('iam_user_sessions')) {
            return;
        }

        $table = $schema->createTable('iam_user_sessions');
        $this->uuid($table, 'session_id');
        $table->addColumn('principal_id', 'string', ['length' => 120]);
        $table->addColumn('principal_type', 'string', ['length' => 60]);
        $table->addColumn('ip_address', 'string', ['length' => 45, 'notnull' => false]);
        $table->addColumn('user_agent', 'string', ['length' => 500, 'notnull' => false]);
        $table->addColumn('started_at', 'datetime_immutable');
        $table->addColumn('last_activity_at', 'datetime_immutable');
        $table->addColumn('ended_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('revoked_at', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['session_id']);
        $table->addIndex(['principal_id', 'principal_type', 'ended_at'], 'iam_user_sessions_principal_active_idx');
        $table->addIndex(['started_at'], 'iam_user_sessions_time_idx');
    }

    private function createPasswordResets(Schema $schema): void
    {
        if ($schema->hasTable('iam_password_resets')) {
            return;
        }

        $table = $schema->createTable('iam_password_resets');
        $this->uuid($table, 'reset_id');
        $table->addColumn('user_id', 'integer');
        $table->addColumn('token_hash', 'string', ['length' => 128]);
        $table->addColumn('ip_address', 'string', ['length' => 45, 'notnull' => false]);
        $table->addColumn('user_agent', 'string', ['length' => 500, 'notnull' => false]);
        $table->addColumn('expires_at', 'datetime_immutable');
        $table->addColumn('consumed_at', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['reset_id']);
        $table->addUniqueIndex(['token_hash'], 'iam_password_resets_token_unique');
        $table->addIndex(['user_id', 'consumed_at'], 'iam_password_resets_user_idx');
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'iam_password_resets_user_fk');
    }

    private function createOtpChallenges(Schema $schema): void
    {
        if ($schema->hasTable('iam_otp_challenges')) {
            return;
        }

        $table = $schema->createTable('iam_otp_challenges');
        $this->uuid($table, 'challenge_id');
        $table->addColumn('user_id', 'integer');
        $table->addColumn('purpose', 'string', ['length' => 60]);
        $table->addColumn('code_hash', 'string', ['length' => 128]);
        $table->addColumn('expires_at', 'datetime_immutable');
        $table->addColumn('consumed_at', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['challenge_id']);
        $table->addIndex(['user_id', 'purpose', 'consumed_at'], 'iam_otp_challenges_lookup_idx');
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'iam_otp_challenges_user_fk');
    }

    private function createDeliveries(Schema $schema): void
    {
        if ($schema->hasTable('iam_deliveries')) {
            return;
        }

        $table = $schema->createTable('iam_deliveries');
        $this->uuid($table, 'delivery_id');
        $table->addColumn('user_id', 'integer');
        $table->addColumn('channel', 'string', ['length' => 40]);
        $table->addColumn('purpose', 'string', ['length' => 80]);
        $table->addColumn('destination_hash', 'string', ['length' => 128]);
        $table->addColumn('payload_json', 'json', ['notnull' => false]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'queued']);
        $table->addColumn('sent_at', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['delivery_id']);
        $table->addIndex(['user_id', 'purpose'], 'iam_deliveries_user_purpose_idx');
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'iam_deliveries_user_fk');
    }

    private function createServiceCredentials(Schema $schema): void
    {
        if ($schema->hasTable('iam_service_credentials')) {
            return;
        }

        $table = $schema->createTable('iam_service_credentials');
        $this->uuid($table, 'credential_id');
        $table->addColumn('name', 'string', ['length' => 160]);
        $table->addColumn('key_prefix', 'string', ['length' => 24]);
        $table->addColumn('key_hash', 'string', ['length' => 128]);
        $table->addColumn('scopes_json', 'json');
        $table->addColumn('created_by', 'integer');
        $table->addColumn('expires_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('revoked_at', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['credential_id']);
        $table->addUniqueIndex(['key_hash'], 'iam_service_credentials_key_hash_unique');
        $table->addForeignKeyConstraint('users', ['created_by'], ['id'], ['onDelete' => 'CASCADE'], 'iam_service_credentials_user_fk');
    }

    private function createUserSources(Schema $schema): void
    {
        if ($schema->hasTable('iam_user_sources')) {
            return;
        }

        $table = $schema->createTable('iam_user_sources');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('user_id', 'integer');
        $table->addColumn('source_module', 'string', ['length' => 80]);
        $table->addColumn('source_type', 'string', ['length' => 80]);
        $table->addColumn('source_id', 'string', ['length' => 120]);
        $table->addColumn('metadata_json', 'json', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['source_module', 'source_type', 'source_id'], 'iam_user_sources_origin_unique');
        $table->addIndex(['user_id'], 'iam_user_sources_user_idx');
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'iam_user_sources_user_fk');
    }
}
