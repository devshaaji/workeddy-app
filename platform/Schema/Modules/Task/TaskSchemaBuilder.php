<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Task;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

final class TaskSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'task';
    }

    public function tables(): array
    {
        return ['tasks'];
    }

    public function build(Schema $schema): void
    {
        if ($schema->hasTable('tasks')) {
            return;
        }

        $table = $schema->createTable('tasks');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('organization_id', 'integer');
        $table->addColumn('worksite_id', 'integer', ['notnull' => false]);
        $table->addColumn('department_id', 'integer', ['notnull' => false]);
        $table->addColumn('job_role_id', 'integer', ['notnull' => false]);
        $table->addColumn('name', 'string', ['length' => 180]);
        $table->addColumn('assessment_model', 'string', ['length' => 40, 'default' => 'reba']);
        $table->addColumn('task_code', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'active']);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $this->timestamps($table);
        $this->softDeletes($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'tasks_uuid_unique');
        $table->addIndex(['organization_id', 'status'], 'tasks_org_status_idx');
        $table->addIndex(['worksite_id', 'department_id', 'job_role_id'], 'tasks_scope_idx');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'tasks_org_fk');
        $table->addForeignKeyConstraint('worksites', ['worksite_id'], ['id'], ['onDelete' => 'SET NULL'], 'tasks_worksite_fk');
        $table->addForeignKeyConstraint('departments', ['department_id'], ['id'], ['onDelete' => 'SET NULL'], 'tasks_department_fk');
        $table->addForeignKeyConstraint('job_roles', ['job_role_id'], ['id'], ['onDelete' => 'SET NULL'], 'tasks_job_role_fk');
    }
}
