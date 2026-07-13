<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Notification;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

final class NotificationSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'notification';
    }

    public function tables(): array
    {
        return ['notification_logs', 'notification_log_attempts', 'notification_preferences', 'notification_in_app_messages'];
    }

    public function build(Schema $schema): void
    {
        if (!$schema->hasTable('notification_logs')) {
            $table = $schema->createTable('notification_logs');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $this->uuid($table, 'uuid');
            $table->addColumn('notification_type', 'string', ['length' => 255]);
            $table->addColumn('recipient_type', 'string', ['length' => 255]);
            $table->addColumn('recipient_id', 'string', ['length' => 255]);
            $table->addColumn('recipient_name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('recipient_email', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('recipient_phone', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('channel', 'string', ['length' => 50]);
            $table->addColumn('provider', 'string', ['length' => 100]);
            $table->addColumn('subject', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('message_preview', 'text', ['notnull' => false]);
            $table->addColumn('status', 'string', ['length' => 50]);
            $table->addColumn('attempt_count', 'integer', ['default' => 0]);
            $table->addColumn('failure_reason', 'text', ['notnull' => false]);
            $table->addColumn('failure_type', 'string', ['length' => 50, 'notnull' => false]);
            $table->addColumn('provider_message_id', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('metadata_json', 'json', ['notnull' => false]);
            $table->addColumn('queued_at', 'datetime_immutable', ['notnull' => false]);
            $table->addColumn('sent_at', 'datetime_immutable', ['notnull' => false]);
            $table->addColumn('failed_at', 'datetime_immutable', ['notnull' => false]);
            $this->timestamps($table);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uuid'], 'notification_logs_uuid_unique');
            $table->addIndex(['notification_type'], 'notification_logs_type_idx');
            $table->addIndex(['recipient_id', 'recipient_type'], 'notification_logs_recipient_idx');
            $table->addIndex(['status'], 'notification_logs_status_idx');
            $table->addIndex(['created_at'], 'notification_logs_created_at_idx');
        }

        if (!$schema->hasTable('notification_preferences')) {
            $table = $schema->createTable('notification_preferences');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('recipient_type', 'string', ['length' => 255]);
            $table->addColumn('recipient_id', 'string', ['length' => 255]);
            $table->addColumn('in_app_enabled', 'boolean', ['default' => true]);
            $table->addColumn('email_enabled', 'boolean', ['default' => true]);
            $table->addColumn('sms_enabled', 'boolean', ['default' => true]);
            $table->addColumn('whatsapp_enabled', 'boolean', ['default' => true]);
            $this->timestamps($table);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['recipient_type', 'recipient_id'], 'notification_preferences_recipient_unique');
        }

        if (!$schema->hasTable('notification_log_attempts')) {
            $table = $schema->createTable('notification_log_attempts');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $this->uuid($table, 'uuid');
            $table->addColumn('log_uuid', 'string', ['length' => 36]);
            $table->addColumn('channel', 'string', ['length' => 50]);
            $table->addColumn('provider_key', 'string', ['length' => 100]);
            $table->addColumn('attempt_count', 'integer', ['default' => 0]);
            $table->addColumn('status', 'string', ['length' => 50]);
            $table->addColumn('failure_reason', 'text', ['notnull' => false]);
            $table->addColumn('failure_type', 'string', ['length' => 50, 'notnull' => false]);
            $table->addColumn('provider_message_id', 'string', ['length' => 255, 'notnull' => false]);
            $this->timestamps($table);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uuid'], 'notification_log_attempts_uuid_unique');
            $table->addIndex(['log_uuid'], 'notification_log_attempts_log_uuid_idx');
            $table->addIndex(['created_at'], 'notification_log_attempts_created_at_idx');
        }

        if (!$schema->hasTable('notification_in_app_messages')) {
            $table = $schema->createTable('notification_in_app_messages');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $this->uuid($table, 'uuid');
            $table->addColumn('recipient_type', 'string', ['length' => 255]);
            $table->addColumn('recipient_id', 'string', ['length' => 255]);
            $table->addColumn('notification_type', 'string', ['length' => 255]);
            $table->addColumn('subject', 'string', ['length' => 255]);
            $table->addColumn('body', 'text');
            $table->addColumn('metadata_json', 'json', ['notnull' => false]);
            $table->addColumn('read_at', 'datetime_immutable', ['notnull' => false]);
            $this->timestamps($table);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uuid'], 'notification_in_app_messages_uuid_unique');
            $table->addIndex(['recipient_type', 'recipient_id'], 'notification_in_app_messages_recipient_idx');
            $table->addIndex(['read_at'], 'notification_in_app_messages_read_at_idx');
        }
    }
}
