<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WorkEddy\Platform\Schema\Modules\Assessment\AssessmentSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Organization\OrganizationSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\WorkerVoice\WorkerVoiceSchemaBuilder;

final class Version20260710030000_AddPilotEvidenceSources extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pilot_sites, validation_reviews, and supervisor_feedback tables for pilot dashboard evidence sources.';
    }

    public function up(Schema $schema): void
    {
        (new OrganizationSchemaBuilder())->build($schema);
        (new AssessmentSchemaBuilder())->build($schema);
        (new WorkerVoiceSchemaBuilder())->build($schema);
    }

    public function down(Schema $schema): void
    {
        unset($schema);
    }
}
