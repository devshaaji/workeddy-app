<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710060000_AddTaskAssessmentModel extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add required assessment model to tasks and backfill existing rows.';
    }

    public function up(Schema $schema): void
    {
        unset($schema);

        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->tablesExist(['tasks'])) {
            $table = $schemaManager->introspectTable('tasks');
            if (!$table->hasColumn('assessment_model')) {
                $this->addSql("ALTER TABLE tasks ADD assessment_model VARCHAR(40) NOT NULL DEFAULT 'reba' AFTER name");
            }
        }

        $this->addSql("UPDATE tasks SET assessment_model = 'reba' WHERE assessment_model IS NULL OR assessment_model = ''");
    }

    public function down(Schema $schema): void
    {
        unset($schema);

        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->tablesExist(['tasks'])) {
            $table = $schemaManager->introspectTable('tasks');
            if ($table->hasColumn('assessment_model')) {
                $this->addSql('ALTER TABLE tasks DROP COLUMN assessment_model');
            }
        }
    }
}
