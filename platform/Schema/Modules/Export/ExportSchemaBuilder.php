<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Export;

use Doctrine\DBAL\Schema\Schema;
use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;

final class ExportSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'export';
    }

    public function tables(): array
    {
        return ['research_exports', 'research_export_code_maps'];
    }

    public function build(Schema $schema): void
    {
        $this->buildResearchExports($schema);
        $this->buildCodeMaps($schema);
    }

    private function buildResearchExports(Schema $schema): void
    {
        if ($schema->hasTable('research_exports')) {
            return;
        }

        $table = $schema->createTable('research_exports');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('organization_id', 'integer');
        $this->uuid($table, 'organization_uuid');
        $table->addColumn('dataset', 'string', ['length' => 80]);
        $table->addColumn('format', 'string', ['length' => 20]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'pending']);
        $table->addColumn('filters_json', 'text');
        $table->addColumn('column_schema_json', 'text');
        $table->addColumn('deidentification_profile', 'string', ['length' => 120]);
        $this->uuid($table, 'storage_file_uuid', false);
        $table->addColumn('row_count', 'integer', ['notnull' => false]);
        $table->addColumn('generated_by_user_id', 'integer', ['notnull' => false]);
        $table->addColumn('generated_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('expires_at', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'research_exports_uuid_unique');
        $table->addIndex(['organization_id', 'status'], 'research_exports_org_status_idx');
        $table->addIndex(['storage_file_uuid'], 'research_exports_storage_idx');
    }

    private function buildCodeMaps(Schema $schema): void
    {
        if ($schema->hasTable('research_export_code_maps')) {
            return;
        }

        $table = $schema->createTable('research_export_code_maps');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'export_uuid');
        $table->addColumn('entity_type', 'string', ['length' => 80]);
        $this->uuid($table, 'entity_uuid', false);
        $table->addColumn('export_code', 'string', ['length' => 80]);
        $this->createdAt($table);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['export_uuid', 'entity_type'], 'research_export_code_maps_export_type_idx');
        $table->addUniqueIndex(['export_uuid', 'entity_type', 'entity_uuid'], 'research_export_code_maps_unique');
    }
}
