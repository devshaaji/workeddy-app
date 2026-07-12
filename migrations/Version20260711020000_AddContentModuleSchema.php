<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WorkEddy\Platform\Schema\Modules\Content\ContentSchemaBuilder;

final class Version20260711020000_AddContentModuleSchema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the Content module schema tables.';
    }

    public function up(Schema $schema): void
    {
        (new ContentSchemaBuilder())->build($schema);
    }

    public function down(Schema $schema): void
    {
        foreach (['content_media', 'content_page_revisions', 'content_pages'] as $table) {
            if ($schema->hasTable($table)) {
                $schema->dropTable($table);
            }
        }
    }
}
