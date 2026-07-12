<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Audit;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

final class AuditSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'audit';
    }

    public function tables(): array
    {
        return ['audit_logs'];
    }

    public function build(Schema $schema): void
    {
        if ($schema->hasTable('audit_logs')) {
            return;
        }

        $table = $schema->createTable('audit_logs');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('actor_id', 'integer', ['default' => 0]);
        $table->addColumn('action', 'string', ['length' => 120]);
        $table->addColumn('entity_type', 'string', ['length' => 120]);
        $table->addColumn('entity_id', 'string', ['length' => 120]);
        $table->addColumn('module', 'string', ['length' => 80]);
        $table->addColumn('before_state', 'json', ['notnull' => false]);
        $table->addColumn('after_state', 'json', ['notnull' => false]);
        $table->addColumn('ip_address', 'string', ['length' => 45, 'notnull' => false]);
        $this->createdAt($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'audit_logs_uuid_unique');
        $table->addIndex(['actor_id', 'created_at'], 'audit_logs_actor_created_idx');
        $table->addIndex(['module', 'created_at'], 'audit_logs_module_created_idx');
        $table->addIndex(['entity_type', 'entity_id'], 'audit_logs_entity_idx');
        $table->addIndex(['action', 'created_at'], 'audit_logs_action_created_idx');
    }
}
