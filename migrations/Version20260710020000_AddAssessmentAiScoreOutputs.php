<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WorkEddy\Platform\Schema\Modules\Assessment\AssessmentSchemaBuilder;

final class Version20260710020000_AddAssessmentAiScoreOutputs extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ai_score_outputs table for reviewer-facing posture scoring support.';
    }

    public function up(Schema $schema): void
    {
        (new AssessmentSchemaBuilder())->build($schema);
    }

    public function down(Schema $schema): void
    {
        unset($schema);
    }
}
