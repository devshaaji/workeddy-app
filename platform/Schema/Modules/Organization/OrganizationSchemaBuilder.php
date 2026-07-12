<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Organization;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

final class OrganizationSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'organization';
    }

    public function tables(): array
    {
        return [
            'organizations',
            'organization_memberships',
            'user_profiles',
            'worksites',
            'pilot_sites',
            'departments',
            'job_roles',
        ];
    }

    public function build(Schema $schema): void
    {
        $this->createOrganizations($schema);
        $this->createUserProfiles($schema);
        $this->createWorksites($schema);
        $this->createPilotSites($schema);
        $this->createDepartments($schema);
        $this->createJobRoles($schema);
        $this->createOrganizationMemberships($schema);
    }

    private function createOrganizations(Schema $schema): void
    {
        if ($schema->hasTable('organizations')) {
            return;
        }

        $table = $schema->createTable('organizations');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('name', 'string', ['length' => 180]);
        $table->addColumn('slug', 'string', ['length' => 180]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'active']);
        $table->addColumn('contact_email', 'string', ['length' => 240, 'notnull' => false]);
        $table->addColumn('phone', 'string', ['length' => 40, 'notnull' => false]);
        $this->timestamps($table);
        $this->softDeletes($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'organizations_uuid_unique');
        $table->addUniqueIndex(['slug'], 'organizations_slug_unique');
        $table->addIndex(['status'], 'organizations_status_idx');
    }

    private function createUserProfiles(Schema $schema): void
    {
        if ($schema->hasTable('user_profiles')) {
            return;
        }

        $table = $schema->createTable('user_profiles');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('user_id', 'integer');
        $table->addColumn('full_name', 'string', ['length' => 240]);
        $table->addColumn('phone', 'string', ['length' => 40, 'notnull' => false]);
        $table->addColumn('job_title', 'string', ['length' => 180, 'notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'user_profiles_uuid_unique');
        $table->addUniqueIndex(['user_id'], 'user_profiles_user_unique');
        $table->addIndex(['full_name'], 'user_profiles_full_name_idx');
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'user_profiles_user_fk');
    }

    private function createWorksites(Schema $schema): void
    {
        if ($schema->hasTable('worksites')) {
            return;
        }

        $table = $schema->createTable('worksites');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('organization_id', 'integer');
        $table->addColumn('name', 'string', ['length' => 180]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'active']);
        $table->addColumn('location', 'string', ['length' => 255, 'notnull' => false]);
        $this->timestamps($table);
        $this->softDeletes($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'worksites_uuid_unique');
        $table->addIndex(['organization_id', 'status'], 'worksites_org_status_idx');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'worksites_org_fk');
    }

    private function createDepartments(Schema $schema): void
    {
        if ($schema->hasTable('departments')) {
            return;
        }

        $table = $schema->createTable('departments');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('organization_id', 'integer');
        $table->addColumn('worksite_id', 'integer', ['notnull' => false]);
        $table->addColumn('parent_department_id', 'integer', ['notnull' => false]);
        $table->addColumn('name', 'string', ['length' => 180]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'active']);
        $this->timestamps($table);
        $this->softDeletes($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'departments_uuid_unique');
        $table->addIndex(['organization_id', 'worksite_id'], 'departments_org_worksite_idx');
        $table->addIndex(['parent_department_id'], 'departments_parent_idx');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'departments_org_fk');
        $table->addForeignKeyConstraint('worksites', ['worksite_id'], ['id'], ['onDelete' => 'SET NULL'], 'departments_worksite_fk');
        $table->addForeignKeyConstraint('departments', ['parent_department_id'], ['id'], ['onDelete' => 'SET NULL'], 'departments_parent_fk');
    }

    private function createPilotSites(Schema $schema): void
    {
        if ($schema->hasTable('pilot_sites')) {
            return;
        }

        $table = $schema->createTable('pilot_sites');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('organization_id', 'integer');
        $this->uuid($table, 'organization_uuid');
        $table->addColumn('worksite_id', 'integer');
        $this->uuid($table, 'worksite_uuid');
        $table->addColumn('enrollment_date', 'date_immutable');
        $table->addColumn('pilot_status', 'string', ['length' => 40, 'default' => 'enrolled']);
        $table->addColumn('target_worker_count', 'integer', ['default' => 0]);
        $table->addColumn('actual_worker_count', 'integer', ['default' => 0]);
        $table->addColumn('industry', 'string', ['length' => 180, 'notnull' => false]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $this->timestamps($table);
        $this->softDeletes($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'pilot_sites_uuid_unique');
        $table->addUniqueIndex(['organization_id', 'worksite_id'], 'pilot_sites_org_worksite_unique');
        $table->addIndex(['organization_id', 'pilot_status'], 'pilot_sites_org_status_idx');
        $table->addIndex(['industry'], 'pilot_sites_industry_idx');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'pilot_sites_org_fk');
        $table->addForeignKeyConstraint('worksites', ['worksite_id'], ['id'], ['onDelete' => 'CASCADE'], 'pilot_sites_worksite_fk');
    }

    private function createJobRoles(Schema $schema): void
    {
        if ($schema->hasTable('job_roles')) {
            return;
        }

        $table = $schema->createTable('job_roles');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('organization_id', 'integer');
        $table->addColumn('department_id', 'integer', ['notnull' => false]);
        $table->addColumn('name', 'string', ['length' => 180]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'active']);
        $this->timestamps($table);
        $this->softDeletes($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'job_roles_uuid_unique');
        $table->addIndex(['organization_id', 'department_id'], 'job_roles_org_department_idx');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'job_roles_org_fk');
        $table->addForeignKeyConstraint('departments', ['department_id'], ['id'], ['onDelete' => 'SET NULL'], 'job_roles_department_fk');
    }

    private function createOrganizationMemberships(Schema $schema): void
    {
        if ($schema->hasTable('organization_memberships')) {
            return;
        }

        $table = $schema->createTable('organization_memberships');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('user_id', 'integer');
        $table->addColumn('organization_id', 'integer');
        $table->addColumn('role_id', 'integer');
        $table->addColumn('role_slug', 'string', ['length' => 120]);
        $table->addColumn('worksite_id', 'integer', ['notnull' => false]);
        $table->addColumn('department_id', 'integer', ['notnull' => false]);
        $table->addColumn('job_role_id', 'integer', ['notnull' => false]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'active']);
        $table->addColumn('is_primary', 'boolean', ['default' => true]);
        $this->timestamps($table);
        $this->softDeletes($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'organization_memberships_uuid_unique');
        $table->addUniqueIndex(['user_id', 'organization_id'], 'organization_memberships_user_org_unique');
        $table->addIndex(['organization_id', 'status'], 'organization_memberships_org_status_idx');
        $table->addIndex(['role_id'], 'organization_memberships_role_idx');
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'organization_memberships_user_fk');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'organization_memberships_org_fk');
        $table->addForeignKeyConstraint('iam_roles', ['role_id'], ['id'], ['onDelete' => 'RESTRICT'], 'organization_memberships_role_fk');
        $table->addForeignKeyConstraint('worksites', ['worksite_id'], ['id'], ['onDelete' => 'SET NULL'], 'organization_memberships_worksite_fk');
        $table->addForeignKeyConstraint('departments', ['department_id'], ['id'], ['onDelete' => 'SET NULL'], 'organization_memberships_department_fk');
        $table->addForeignKeyConstraint('job_roles', ['job_role_id'], ['id'], ['onDelete' => 'SET NULL'], 'organization_memberships_job_role_fk');
    }
}
