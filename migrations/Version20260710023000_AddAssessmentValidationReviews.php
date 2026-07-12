<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WorkEddy\Platform\Schema\Modules\Assessment\AssessmentSchemaBuilder;

final class Version20260710023000_AddAssessmentValidationReviews extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add validation_reviews table for independent reviewer agreement tracking.';
    }

    public function up(Schema $schema): void
    {
        (new AssessmentSchemaBuilder())->build($schema);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('validation_reviews')) {
            $schema->dropTable('validation_reviews');
        }
    }
}
