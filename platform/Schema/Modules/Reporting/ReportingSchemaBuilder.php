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
        return ['report_artifacts', 'national_statistics', 'platform_aggregate_metrics'];
    }

    public function build(Schema $schema): void
    {
        $this->createReportArtifacts($schema);
        $this->createNationalStatistics($schema);
        $this->createPlatformAggregateMetrics($schema);
    }

    private function createReportArtifacts(Schema $schema): void
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

    /**
     * Admin-authored, source-cited national context statistics shown on the
     * National Importance dashboard. Every row must carry a source citation
     * (source_name, source_year, source_url) — enforced at the application
     * layer in the Create/Update use cases, not just in the UI.
     */
    private function createNationalStatistics(Schema $schema): void
    {
        if ($schema->hasTable('national_statistics')) {
            return;
        }

        $table = $schema->createTable('national_statistics');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('title', 'string', ['length' => 255]);
        $table->addColumn('value', 'string', ['length' => 120]);
        $table->addColumn('unit', 'string', ['length' => 60, 'notnull' => false]);
        $table->addColumn('category', 'string', ['length' => 60]);
        $table->addColumn('industry_relevance', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('source_name', 'string', ['length' => 255]);
        $table->addColumn('source_year', 'smallint');
        $table->addColumn('source_url', 'string', ['length' => 500]);
        $table->addColumn('is_published', 'boolean', ['default' => true]);
        $table->addColumn('date_added', 'date_immutable');
        $table->addColumn('created_by_user_id', 'integer', ['notnull' => false]);
        $table->addColumn('updated_by_user_id', 'integer', ['notnull' => false]);
        $this->timestamps($table);
        $this->softDeletes($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'uniq_national_statistics_uuid');
        $table->addIndex(['category', 'is_published'], 'idx_national_statistics_category_published');
        $table->addIndex(['source_year'], 'idx_national_statistics_source_year');
        $table->addForeignKeyConstraint('users', ['created_by_user_id'], ['id'], ['onDelete' => 'SET NULL'], 'national_statistics_created_by_fk');
        $table->addForeignKeyConstraint('users', ['updated_by_user_id'], ['id'], ['onDelete' => 'SET NULL'], 'national_statistics_updated_by_fk');
    }

    /**
     * Pre-computed, dated cache of platform-wide (cross-organization) metrics
     * for the National Importance dashboard. Populated by a nightly cron
     * (see cronjobs/national-metrics-refresh.php), not computed on page load,
     * so cited figures stay reproducible (generated_at is shown as the
     * citation date wherever a metric is displayed or exported).
     */
    private function createPlatformAggregateMetrics(Schema $schema): void
    {
        if ($schema->hasTable('platform_aggregate_metrics')) {
            return;
        }

        $table = $schema->createTable('platform_aggregate_metrics');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('metric_key', 'string', ['length' => 100]);
        $table->addColumn('metric_name', 'string', ['length' => 180]);
        $table->addColumn('value_json', 'text');
        $table->addColumn('industry', 'string', ['length' => 180, 'notnull' => false]);
        $table->addColumn('date_range_start', 'date_immutable', ['notnull' => false]);
        $table->addColumn('date_range_end', 'date_immutable', ['notnull' => false]);
        $table->addColumn('generated_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addIndex(['metric_key', 'industry', 'generated_at'], 'idx_platform_agg_metrics_lookup');
        $table->addIndex(['generated_at'], 'idx_platform_agg_metrics_generated');
    }
}
