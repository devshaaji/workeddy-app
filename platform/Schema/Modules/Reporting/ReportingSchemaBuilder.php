<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Reporting;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

final class ReportingSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'reporting';
    }

    public function tables(): array
    {
        return ['report_artifacts'];
    }

    public function build(Schema $schema): void
    {
        if ($schema->hasTable('report_artifacts')) {
            return;
        }

        $table = $schema->createTable('report_artifacts');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true]);
        $this->uuid($table, 'uuid');
        $this->uuid($table, 'organization_uuid', false);
        $table->addColumn('report_type', 'string', ['length' => 64]);
        $this->uuid($table, 'source_uuid', false);
        $this->uuid($table, 'previous_artifact_uuid', false);
        $table->addColumn('regeneration_reason', 'text', ['notnull' => false]);
        $table->addColumn('format', 'string', ['length' => 16]);
        $this->uuid($table, 'storage_file_uuid');
        $table->addColumn('template_name', 'string', ['length' => 100]);
        $table->addColumn('template_version', 'string', ['length' => 32, 'default' => 'v1']);
        $table->addColumn('snapshot_hash', 'string', ['length' => 64]);
        $table->addColumn('snapshot_payload', 'text', ['notnull' => false]);
        $table->addColumn('generated_by_user_id', 'integer', ['unsigned' => true, 'notnull' => false]);
        $table->addColumn('generated_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'uniq_report_artifacts_uuid');
        $table->addIndex(['report_type', 'source_uuid'], 'idx_report_artifacts_type_source');
        $table->addIndex(['previous_artifact_uuid'], 'idx_report_artifacts_previous');
        $table->addIndex(['storage_file_uuid'], 'idx_report_artifacts_storage_file');
        $table->addIndex(['organization_uuid', 'generated_at'], 'idx_report_artifacts_org_generated');
    }
}
