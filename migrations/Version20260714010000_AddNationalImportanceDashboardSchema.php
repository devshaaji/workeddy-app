<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WorkEddy\Platform\Schema\Modules\Reporting\ReportingSchemaBuilder;

final class Version20260714010000_AddNationalImportanceDashboardSchema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add national_statistics and platform_aggregate_metrics tables for the National Importance dashboard.';
    }

    public function up(Schema $schema): void
    {
        (new ReportingSchemaBuilder())->build($schema);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('platform_aggregate_metrics')) {
            $schema->dropTable('platform_aggregate_metrics');
        }

        if ($schema->hasTable('national_statistics')) {
            $schema->dropTable('national_statistics');
        }
    }
}
