<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport;

use Doctrine\DBAL\Schema\Schema;

final class TransportSchema
{
    public static function apply(Schema $schema): void
    {
        if (!$schema->hasTable('transport_destinations')) {
            $table = $schema->createTable('transport_destinations');
            $table->addColumn('name', 'string', ['length' => 128]);
            $table->addColumn('driver', 'string', ['length' => 64]);
            $table->addColumn('base_url', 'string', ['length' => 512, 'notnull' => false]);
            $table->addColumn('endpoint', 'string', ['length' => 512, 'notnull' => false]);
            $table->addColumn('auth_type', 'string', ['length' => 64, 'default' => 'none']);
            $table->addColumn('credentials_secret', 'text', ['notnull' => false]);
            $table->addColumn('enabled', 'boolean', ['default' => true]);
            $table->addColumn('timeout_seconds', 'integer', ['default' => 15]);
            $table->addColumn('retry_policy_json', 'text', ['notnull' => false]);
            $table->addColumn('fallback_destinations_json', 'text', ['notnull' => false]);
            $table->addColumn('created_at', 'datetime_immutable');
            $table->addColumn('updated_at', 'datetime_immutable');
            $table->setPrimaryKey(['name']);
            $table->addIndex(['driver', 'enabled'], 'transport_dest_driver_enabled_idx');
        }

        if (!$schema->hasTable('transport_outbox')) {
            $table = $schema->createTable('transport_outbox');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('uuid', 'string', ['length' => 36]);
            $table->addColumn('destination', 'string', ['length' => 128]);
            $table->addColumn('topic', 'string', ['length' => 160]);
            $table->addColumn('payload_json', 'text');
            $table->addColumn('headers_json', 'text', ['notnull' => false]);
            $table->addColumn('priority', 'string', ['length' => 32, 'default' => 'normal']);
            $table->addColumn('status', 'string', ['length' => 32]);
            $table->addColumn('attempt_count', 'integer', ['default' => 0]);
            $table->addColumn('max_attempts', 'integer', ['default' => 10]);
            $table->addColumn('next_attempt_at', 'datetime_immutable', ['notnull' => false]);
            $table->addColumn('last_attempt_at', 'datetime_immutable', ['notnull' => false]);
            $table->addColumn('delivered_at', 'datetime_immutable', ['notnull' => false]);
            $table->addColumn('failed_at', 'datetime_immutable', ['notnull' => false]);
            $table->addColumn('error_message', 'text', ['notnull' => false]);
            $table->addColumn('idempotency_key', 'string', ['length' => 160, 'notnull' => false]);
            $table->addColumn('correlation_id', 'string', ['length' => 160, 'notnull' => false]);
            $table->addColumn('created_at', 'datetime_immutable');
            $table->addColumn('updated_at', 'datetime_immutable');
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uuid'], 'transport_outbox_uuid_uidx');
            $table->addIndex(['status', 'next_attempt_at'], 'transport_outbox_status_next_idx');
            $table->addIndex(['destination'], 'transport_outbox_destination_idx');
            $table->addIndex(['topic'], 'transport_outbox_topic_idx');
            $table->addIndex(['idempotency_key'], 'transport_outbox_idempotency_idx');
            $table->addIndex(['correlation_id'], 'transport_outbox_correlation_idx');
            $table->addIndex(['created_at'], 'transport_outbox_created_idx');
        }

        if (!$schema->hasTable('transport_outbox_attempts')) {
            $table = $schema->createTable('transport_outbox_attempts');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('message_uuid', 'string', ['length' => 36]);
            $table->addColumn('destination', 'string', ['length' => 128]);
            $table->addColumn('driver', 'string', ['length' => 64]);
            $table->addColumn('success', 'boolean');
            $table->addColumn('status_code', 'integer', ['notnull' => false]);
            $table->addColumn('response_body', 'text', ['notnull' => false]);
            $table->addColumn('remote_message_id', 'string', ['length' => 160, 'notnull' => false]);
            $table->addColumn('error_message', 'text', ['notnull' => false]);
            $table->addColumn('retryable', 'boolean', ['default' => false]);
            $table->addColumn('attempted_at', 'datetime_immutable');
            $table->setPrimaryKey(['id']);
            $table->addIndex(['message_uuid'], 'transport_attempts_message_idx');
            $table->addIndex(['destination'], 'transport_attempts_destination_idx');
            $table->addIndex(['attempted_at'], 'transport_attempts_time_idx');
        }

        if (!$schema->hasTable('transport_inbound_sources')) {
            $table = $schema->createTable('transport_inbound_sources');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('name', 'string', ['length' => 128]);
            $table->addColumn('type', 'string', ['length' => 64]);
            $table->addColumn('enabled', 'boolean', ['default' => true]);
            $table->addColumn('auth_type', 'string', ['length' => 64, 'default' => 'none']);
            $table->addColumn('secret_hash', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('allowed_topics_json', 'text', ['notnull' => false]);
            $table->addColumn('allowed_ip_ranges_json', 'text', ['notnull' => false]);
            $table->addColumn('require_signature', 'boolean', ['default' => false]);
            $table->addColumn('signature_header', 'string', ['length' => 128, 'default' => 'X-Transport-Signature']);
            $table->addColumn('timestamp_header', 'string', ['length' => 128, 'default' => 'X-Transport-Timestamp']);
            $table->addColumn('max_clock_skew_seconds', 'integer', ['default' => 300]);
            $table->addColumn('created_at', 'datetime_immutable');
            $table->addColumn('updated_at', 'datetime_immutable');
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['name'], 'transport_inbound_sources_name_uidx');
            $table->addIndex(['type', 'enabled'], 'transport_inbound_sources_type_enabled_idx');
        }

        if (!$schema->hasTable('transport_inbox')) {
            $table = $schema->createTable('transport_inbox');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('uuid', 'string', ['length' => 36]);
            $table->addColumn('source', 'string', ['length' => 128]);
            $table->addColumn('topic', 'string', ['length' => 160]);
            $table->addColumn('payload_json', 'text');
            $table->addColumn('headers_json', 'text', ['notnull' => false]);
            $table->addColumn('raw_message', 'text', ['notnull' => false]);
            $table->addColumn('status', 'string', ['length' => 32]);
            $table->addColumn('idempotency_key', 'string', ['length' => 160, 'notnull' => false]);
            $table->addColumn('correlation_id', 'string', ['length' => 160, 'notnull' => false]);
            $table->addColumn('remote_message_id', 'string', ['length' => 160, 'notnull' => false]);
            $table->addColumn('received_ack_required', 'boolean', ['default' => true]);
            $table->addColumn('processed_ack_required', 'boolean', ['default' => false]);
            $table->addColumn('received_ack_sent_at', 'datetime_immutable', ['notnull' => false]);
            $table->addColumn('processed_ack_sent_at', 'datetime_immutable', ['notnull' => false]);
            $table->addColumn('attempt_count', 'integer', ['default' => 0]);
            $table->addColumn('max_attempts', 'integer', ['default' => 10]);
            $table->addColumn('next_attempt_at', 'datetime_immutable', ['notnull' => false]);
            $table->addColumn('received_at', 'datetime_immutable');
            $table->addColumn('processing_started_at', 'datetime_immutable', ['notnull' => false]);
            $table->addColumn('processed_at', 'datetime_immutable', ['notnull' => false]);
            $table->addColumn('failed_at', 'datetime_immutable', ['notnull' => false]);
            $table->addColumn('error_message', 'text', ['notnull' => false]);
            $table->addColumn('last_error_code', 'string', ['length' => 128, 'notnull' => false]);
            $table->addColumn('created_at', 'datetime_immutable');
            $table->addColumn('updated_at', 'datetime_immutable');
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uuid'], 'transport_inbox_uuid_uidx');
            $table->addIndex(['status', 'next_attempt_at'], 'transport_inbox_status_next_idx');
            $table->addIndex(['source', 'idempotency_key'], 'transport_inbox_source_idempotency_idx');
            $table->addIndex(['source', 'remote_message_id'], 'transport_inbox_source_remote_idx');
            $table->addIndex(['topic'], 'transport_inbox_topic_idx');
            $table->addIndex(['correlation_id'], 'transport_inbox_correlation_idx');
            $table->addIndex(['received_at'], 'transport_inbox_received_idx');
        }

        if (!$schema->hasTable('transport_inbox_attempts')) {
            $table = $schema->createTable('transport_inbox_attempts');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('inbox_id', 'integer');
            $table->addColumn('attempt_number', 'integer');
            $table->addColumn('status', 'string', ['length' => 32]);
            $table->addColumn('handler', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('started_at', 'datetime_immutable');
            $table->addColumn('finished_at', 'datetime_immutable', ['notnull' => false]);
            $table->addColumn('error_message', 'text', ['notnull' => false]);
            $table->addColumn('error_code', 'string', ['length' => 128, 'notnull' => false]);
            $table->addColumn('retryable', 'boolean', ['default' => false]);
            $table->addColumn('created_at', 'datetime_immutable');
            $table->setPrimaryKey(['id']);
            $table->addIndex(['inbox_id'], 'transport_inbox_attempts_inbox_idx');
        }
    }
}
