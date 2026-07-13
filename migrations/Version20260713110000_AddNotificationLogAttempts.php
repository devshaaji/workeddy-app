<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713110000_AddNotificationLogAttempts extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds notification delivery attempt history for provider failover and channel fallback tracing.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('notification_log_attempts')) {
            return;
        }

        $table = $schema->createTable('notification_log_attempts');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('uuid', 'string', ['length' => 36]);
        $table->addColumn('log_uuid', 'string', ['length' => 36]);
        $table->addColumn('channel', 'string', ['length' => 50]);
        $table->addColumn('provider_key', 'string', ['length' => 100]);
        $table->addColumn('attempt_count', 'integer', ['default' => 0]);
        $table->addColumn('status', 'string', ['length' => 50]);
        $table->addColumn('failure_reason', 'text', ['notnull' => false]);
        $table->addColumn('failure_type', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('provider_message_id', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'notification_log_attempts_uuid_unique');
        $table->addIndex(['log_uuid'], 'notification_log_attempts_log_uuid_idx');
        $table->addIndex(['created_at'], 'notification_log_attempts_created_at_idx');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('notification_log_attempts')) {
            $schema->dropTable('notification_log_attempts');
        }
    }
}
