<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260711021000_AddReportArtifactSnapshotPayload extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds snapshot payload persistence to report artifacts.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('report_artifacts')) {
            return;
        }

        $table = $schema->getTable('report_artifacts');
        if (!$table->hasColumn('snapshot_payload')) {
            $table->addColumn('snapshot_payload', 'text', ['notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('report_artifacts')) {
            return;
        }

        $table = $schema->getTable('report_artifacts');
        if ($table->hasColumn('snapshot_payload')) {
            $table->dropColumn('snapshot_payload');
        }
    }
}
