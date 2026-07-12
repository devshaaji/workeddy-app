<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710040000_ExpandCorrectiveActionWorkflowFields extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand corrective action workflow fields for reasons, evidence requirements, rejection reasons, and follow-up dates.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('corrective_action_library')) {
            $table = $schema->getTable('corrective_action_library');
            if (!$table->hasColumn('reason')) {
                $table->addColumn('reason', 'text', ['notnull' => false]);
            }
            if (!$table->hasColumn('evidence_types_json')) {
                $table->addColumn('evidence_types_json', 'text', ['notnull' => false]);
            }
            if (!$table->hasColumn('follow_up_days')) {
                $table->addColumn('follow_up_days', 'integer', ['notnull' => false]);
            }
        }

        if ($schema->hasTable('corrective_action_recommendations')) {
            $table = $schema->getTable('corrective_action_recommendations');
            if (!$table->hasColumn('reason')) {
                $table->addColumn('reason', 'text', ['notnull' => false]);
            }
            if (!$table->hasColumn('follow_up_days')) {
                $table->addColumn('follow_up_days', 'integer', ['notnull' => false]);
            }
            if (!$table->hasColumn('reject_reason')) {
                $table->addColumn('reject_reason', 'text', ['notnull' => false]);
            }
        }

        if ($schema->hasTable('corrective_actions')) {
            $table = $schema->getTable('corrective_actions');
            if (!$table->hasColumn('reason')) {
                $table->addColumn('reason', 'text', ['notnull' => false]);
            }
            if (!$table->hasColumn('evidence_requirements_json')) {
                $table->addColumn('evidence_requirements_json', 'text', ['notnull' => false]);
            }
            if (!$table->hasColumn('reject_reason')) {
                $table->addColumn('reject_reason', 'text', ['notnull' => false]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        unset($schema);
    }
}
