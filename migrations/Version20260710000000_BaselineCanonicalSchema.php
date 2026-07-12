<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WorkEddy\Platform\Schema\CanonicalSchemaBuilder;

final class Version20260710000000_BaselineCanonicalSchema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Build the fresh v2 baseline schema from the canonical schema builders.';
    }

    public function up(Schema $schema): void
    {
        (new CanonicalSchemaBuilder())->buildInto($schema);
    }

    public function down(Schema $schema): void
    {
        unset($schema);
    }
}
