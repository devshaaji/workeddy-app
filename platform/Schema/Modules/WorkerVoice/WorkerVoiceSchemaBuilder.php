<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\WorkerVoice;

use Doctrine\DBAL\Schema\Schema;
use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;

final class WorkerVoiceSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'worker_voice';
    }

    public function tables(): array
    {
        return ['worker_feedback', 'supervisor_feedback'];
    }

    public function build(Schema $schema): void
    {
        $this->buildWorkerFeedback($schema);
        $this->buildSupervisorFeedback($schema);
    }

    private function buildWorkerFeedback(Schema $schema): void
    {
        if ($schema->hasTable('worker_feedback')) {
            return;
        }

        $table = $schema->createTable('worker_feedback');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('organization_id', 'integer');
        $this->uuid($table, 'organization_uuid');
        $table->addColumn('task_id', 'integer', ['notnull' => false]);
        $table->addColumn('task_uuid', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('assessment_uuid', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('worksite_id', 'integer', ['notnull' => false]);
        $table->addColumn('worksite_uuid', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('department_id', 'integer', ['notnull' => false]);
        $table->addColumn('department_uuid', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('job_role_id', 'integer', ['notnull' => false]);
        $table->addColumn('job_role_uuid', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('submitted_by_user_id', 'integer', ['notnull' => false]);
        $table->addColumn('anonymous_status', 'boolean', ['default' => false]);
        $table->addColumn('body_region', 'string', ['length' => 80]);
        $table->addColumn('has_discomfort', 'boolean', ['default' => true]);
        $table->addColumn('discomfort_level', 'smallint');
        $table->addColumn('frequency_level', 'smallint');
        $table->addColumn('difficulty_level', 'smallint');
        $table->addColumn('reporting_comfort_level', 'smallint');
        $table->addColumn('pain_7_day_level', 'smallint');
        $table->addColumn('pain_30_day_level', 'smallint');
        $table->addColumn('suggested_change', 'text', ['notnull' => false]);
        $table->addColumn('metadata_json', 'json', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'worker_feedback_uuid_unique');
        $table->addIndex(['organization_id', 'created_at'], 'worker_feedback_org_created_idx');
        $table->addIndex(['task_uuid'], 'worker_feedback_task_uuid_idx');
        $table->addIndex(['assessment_uuid'], 'worker_feedback_assessment_uuid_idx');
        $table->addIndex(['body_region'], 'worker_feedback_body_region_idx');
        $table->addIndex(['department_uuid'], 'worker_feedback_department_uuid_idx');
        $table->addIndex(['anonymous_status'], 'worker_feedback_anonymous_idx');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'worker_feedback_org_fk');
        $table->addForeignKeyConstraint('tasks', ['task_id'], ['id'], ['onDelete' => 'SET NULL'], 'worker_feedback_task_fk');
        $table->addForeignKeyConstraint('worksites', ['worksite_id'], ['id'], ['onDelete' => 'SET NULL'], 'worker_feedback_worksite_fk');
        $table->addForeignKeyConstraint('departments', ['department_id'], ['id'], ['onDelete' => 'SET NULL'], 'worker_feedback_department_fk');
        $table->addForeignKeyConstraint('job_roles', ['job_role_id'], ['id'], ['onDelete' => 'SET NULL'], 'worker_feedback_job_role_fk');
    }

    private function buildSupervisorFeedback(Schema $schema): void
    {
        if ($schema->hasTable('supervisor_feedback')) {
            return;
        }

        $table = $schema->createTable('supervisor_feedback');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('organization_id', 'integer');
        $this->uuid($table, 'organization_uuid');
        $table->addColumn('task_id', 'integer', ['notnull' => false]);
        $table->addColumn('task_uuid', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('assessment_uuid', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('worksite_id', 'integer', ['notnull' => false]);
        $table->addColumn('worksite_uuid', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('department_id', 'integer', ['notnull' => false]);
        $table->addColumn('department_uuid', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('job_role_id', 'integer', ['notnull' => false]);
        $table->addColumn('job_role_uuid', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('submitted_by_user_id', 'integer');
        $table->addColumn('body_region', 'string', ['length' => 80, 'notnull' => false]);
        $table->addColumn('observed_risk_level', 'string', ['length' => 80]);
        $table->addColumn('observed_issue_type', 'string', ['length' => 120]);
        $table->addColumn('frequency_level', 'smallint');
        $table->addColumn('severity_level', 'smallint');
        $table->addColumn('suggested_change', 'text', ['notnull' => false]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'supervisor_feedback_uuid_unique');
        $table->addIndex(['organization_id', 'created_at'], 'supervisor_feedback_org_created_idx');
        $table->addIndex(['task_uuid'], 'supervisor_feedback_task_uuid_idx');
        $table->addIndex(['assessment_uuid'], 'supervisor_feedback_assessment_uuid_idx');
        $table->addIndex(['body_region'], 'supervisor_feedback_body_region_idx');
        $table->addIndex(['department_uuid'], 'supervisor_feedback_department_uuid_idx');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'supervisor_feedback_org_fk');
        $table->addForeignKeyConstraint('tasks', ['task_id'], ['id'], ['onDelete' => 'SET NULL'], 'supervisor_feedback_task_fk');
        $table->addForeignKeyConstraint('worksites', ['worksite_id'], ['id'], ['onDelete' => 'SET NULL'], 'supervisor_feedback_worksite_fk');
        $table->addForeignKeyConstraint('departments', ['department_id'], ['id'], ['onDelete' => 'SET NULL'], 'supervisor_feedback_department_fk');
        $table->addForeignKeyConstraint('job_roles', ['job_role_id'], ['id'], ['onDelete' => 'SET NULL'], 'supervisor_feedback_job_role_fk');
    }
}
