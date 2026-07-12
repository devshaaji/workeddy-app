<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710190000_AddAssessmentBlurredVideoColumns extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add blurred video storage columns to assessment video tables.';
    }

    public function up(Schema $schema): void
    {
        unset($schema);

        $schemaManager = $this->connection->createSchemaManager();

        if ($schemaManager->tablesExist(['assessment_videos'])) {
            $table = $schemaManager->introspectTable('assessment_videos');
            if (!$table->hasColumn('blurred_storage_file_uuid')) {
                $this->addSql('ALTER TABLE assessment_videos ADD blurred_storage_file_uuid CHAR(36) DEFAULT NULL AFTER pose_video_storage_file_uuid');
            }
        }

        if ($schemaManager->tablesExist(['assessment_video_processing_results'])) {
            $table = $schemaManager->introspectTable('assessment_video_processing_results');
            if (!$table->hasColumn('blurred_storage_file_uuid')) {
                $this->addSql('ALTER TABLE assessment_video_processing_results ADD blurred_storage_file_uuid CHAR(36) DEFAULT NULL AFTER thumbnail_storage_file_uuid');
            }
        }
    }

    public function down(Schema $schema): void
    {
        unset($schema);

        $schemaManager = $this->connection->createSchemaManager();

        if ($schemaManager->tablesExist(['assessment_video_processing_results'])) {
            $table = $schemaManager->introspectTable('assessment_video_processing_results');
            if ($table->hasColumn('blurred_storage_file_uuid')) {
                $this->addSql('ALTER TABLE assessment_video_processing_results DROP COLUMN blurred_storage_file_uuid');
            }
        }

        if ($schemaManager->tablesExist(['assessment_videos'])) {
            $table = $schemaManager->introspectTable('assessment_videos');
            if ($table->hasColumn('blurred_storage_file_uuid')) {
                $this->addSql('ALTER TABLE assessment_videos DROP COLUMN blurred_storage_file_uuid');
            }
        }
    }
}
