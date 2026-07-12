<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260711030000_AddStorageUploadMetadata extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds uploader, image dimension, and checksum metadata to uploads for the admin file manager.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('uploads')) {
            return;
        }

        $table = $schema->getTable('uploads');

        if (!$table->hasColumn('width')) {
            $table->addColumn('width', 'integer', ['notnull' => false]);
        }
        if (!$table->hasColumn('height')) {
            $table->addColumn('height', 'integer', ['notnull' => false]);
        }
        if (!$table->hasColumn('checksum_sha256')) {
            $table->addColumn('checksum_sha256', 'string', ['length' => 64, 'notnull' => false]);
        }
        if (!$table->hasColumn('uploaded_by')) {
            $table->addColumn('uploaded_by', 'integer', ['notnull' => false]);
        }
        if (!$table->hasIndex('uploads_uploaded_by_idx')) {
            $table->addIndex(['uploaded_by'], 'uploads_uploaded_by_idx');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('uploads')) {
            return;
        }

        $table = $schema->getTable('uploads');

        if ($table->hasIndex('uploads_uploaded_by_idx')) {
            $table->dropIndex('uploads_uploaded_by_idx');
        }
        foreach (['width', 'height', 'checksum_sha256', 'uploaded_by'] as $column) {
            if ($table->hasColumn($column)) {
                $table->dropColumn($column);
            }
        }
    }
}
