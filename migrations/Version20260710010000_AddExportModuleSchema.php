<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WorkEddy\Platform\Schema\Modules\Export\ExportSchemaBuilder;

final class Version20260710010000_AddExportModuleSchema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add export module schema for de-identified research exports.';
    }

    public function up(Schema $schema): void
    {
        (new ExportSchemaBuilder())->build($schema);
    }

    public function down(Schema $schema): void
    {
        unset($schema);
    }
}
