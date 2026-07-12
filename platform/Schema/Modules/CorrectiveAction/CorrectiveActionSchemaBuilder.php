<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\CorrectiveAction;

use Doctrine\DBAL\Schema\Schema;
use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;

final class CorrectiveActionSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'corrective_action';
    }

    public function tables(): array
    {
        return ['corrective_action_library', 'recommendation_rules', 'corrective_action_recommendations', 'corrective_actions', 'corrective_action_evidence', 'corrective_action_status_history', 'corrective_action_follow_ups'];
    }

    public function build(Schema $schema): void
    {
        $this->library($schema);
        $this->rules($schema);
        $this->recommendations($schema);
        $this->actions($schema);
        $this->evidence($schema);
        $this->history($schema);
        $this->followUps($schema);
    }

    private function library(Schema $schema): void
    {
        if ($schema->hasTable('corrective_action_library')) {
            return;
        }
        $table = $schema->createTable('corrective_action_library');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('title', 'string', ['length' => 220]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('reason', 'text', ['notnull' => false]);
        $table->addColumn('control_type', 'string', ['length' => 40]);
        $table->addColumn('hierarchy_level', 'string', ['length' => 40]);
        $table->addColumn('risk_factor', 'string', ['length' => 120, 'notnull' => false]);
        $table->addColumn('task_type', 'string', ['length' => 120, 'notnull' => false]);
        $table->addColumn('industry', 'string', ['length' => 120, 'notnull' => false]);
        $table->addColumn('priority', 'string', ['length' => 40, 'default' => 'medium']);
        $table->addColumn('due_days', 'integer', ['default' => 30]);
        $table->addColumn('evidence_required', 'boolean', ['default' => true]);
        $table->addColumn('evidence_types_json', 'text', ['notnull' => false]);
        $table->addColumn('follow_up_days', 'integer', ['notnull' => false]);
        $table->addColumn('is_active', 'boolean', ['default' => true]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'corrective_action_library_uuid_unique');
    }

    private function rules(Schema $schema): void
    {
        if ($schema->hasTable('recommendation_rules')) {
            return;
        }
        $table = $schema->createTable('recommendation_rules');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('condition_json', 'text');
        $table->addColumn('action_json', 'text');
        $table->addColumn('weight', 'integer', ['default' => 0]);
        $table->addColumn('is_active', 'boolean', ['default' => true]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['is_active', 'weight'], 'recommendation_rules_active_weight_idx');
    }

    private function recommendations(Schema $schema): void
    {
        if ($schema->hasTable('corrective_action_recommendations')) {
            return;
        }
        $table = $schema->createTable('corrective_action_recommendations');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('organization_id', 'integer');
        $this->uuid($table, 'organization_uuid');
        $this->uuid($table, 'assessment_uuid');
        $this->uuid($table, 'library_item_uuid', false);
        $table->addColumn('control_code', 'string', ['length' => 160]);
        $table->addColumn('title', 'string', ['length' => 220]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('reason', 'text', ['notnull' => false]);
        $table->addColumn('hierarchy_level', 'string', ['length' => 40]);
        $table->addColumn('control_type', 'string', ['length' => 40]);
        $table->addColumn('priority', 'string', ['length' => 40]);
        $table->addColumn('rank_order', 'integer');
        $table->addColumn('expected_risk_reduction_pct', 'decimal', ['precision' => 6, 'scale' => 2, 'default' => '0.00']);
        $table->addColumn('due_days', 'integer', ['notnull' => false]);
        $table->addColumn('follow_up_days', 'integer', ['notnull' => false]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'generated']);
        $table->addColumn('evidence_json', 'text');
        $table->addColumn('reject_reason', 'text', ['notnull' => false]);
        $table->addColumn('reviewed_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('reviewed_by', 'integer', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'corrective_action_recommendations_uuid_unique');
        $table->addIndex(['assessment_uuid', 'status'], 'corrective_action_recommendations_assessment_status_idx');
    }

    private function actions(Schema $schema): void
    {
        if ($schema->hasTable('corrective_actions')) {
            return;
        }
        $table = $schema->createTable('corrective_actions');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('organization_id', 'integer');
        $this->uuid($table, 'organization_uuid');
        $this->uuid($table, 'assessment_uuid');
        $this->uuid($table, 'recommendation_uuid', false);
        $this->uuid($table, 'library_item_uuid', false);
        $table->addColumn('title', 'string', ['length' => 220]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('reason', 'text', ['notnull' => false]);
        $table->addColumn('control_type', 'string', ['length' => 40]);
        $table->addColumn('hierarchy_level', 'string', ['length' => 40]);
        $table->addColumn('priority', 'string', ['length' => 40]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'open']);
        $table->addColumn('assigned_to_user_id', 'integer', ['notnull' => false]);
        $table->addColumn('assigned_by_user_id', 'integer', ['notnull' => false]);
        $table->addColumn('due_date', 'date_immutable', ['notnull' => false]);
        $table->addColumn('follow_up_assessment_due_date', 'date_immutable', ['notnull' => false]);
        $table->addColumn('evidence_requirements_json', 'text', ['notnull' => false]);
        $table->addColumn('reject_reason', 'text', ['notnull' => false]);
        $table->addColumn('completed_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('verified_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('rejected_at', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'corrective_actions_uuid_unique');
        $table->addIndex(['organization_id', 'status'], 'corrective_actions_org_status_idx');
        $table->addIndex(['assessment_uuid'], 'corrective_actions_assessment_idx');
    }

    private function evidence(Schema $schema): void
    {
        if ($schema->hasTable('corrective_action_evidence')) {
            return;
        }
        $table = $schema->createTable('corrective_action_evidence');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $this->uuid($table, 'action_uuid');
        $this->uuid($table, 'storage_file_uuid');
        $table->addColumn('evidence_type', 'string', ['length' => 40]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('uploaded_by', 'integer');
        $this->createdAt($table);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['action_uuid'], 'corrective_action_evidence_action_idx');
    }

    private function history(Schema $schema): void
    {
        if ($schema->hasTable('corrective_action_status_history')) {
            return;
        }
        $table = $schema->createTable('corrective_action_status_history');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'action_uuid');
        $table->addColumn('status', 'string', ['length' => 40]);
        $table->addColumn('actor_id', 'integer');
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $this->createdAt($table);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['action_uuid', 'created_at'], 'corrective_action_history_action_idx');
    }

    private function followUps(Schema $schema): void
    {
        if ($schema->hasTable('corrective_action_follow_ups')) {
            return;
        }
        $table = $schema->createTable('corrective_action_follow_ups');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'action_uuid');
        $table->addColumn('due_date', 'date_immutable');
        $this->uuid($table, 'follow_up_assessment_uuid', false);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'scheduled']);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['due_date', 'status'], 'corrective_action_followups_due_status_idx');
    }
}
