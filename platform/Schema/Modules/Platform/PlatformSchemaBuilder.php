<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Platform;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

final class PlatformSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'platform';
    }

    public function tables(): array
    {
        return ['idempotency_records', 'system_settings', 'platform_jobs'];
    }

    public function build(Schema $schema): void
    {
        $this->createIdempotencyRecords($schema);
        $this->createSystemSettings($schema);
        $this->createPlatformJobs($schema);
    }

    private function createIdempotencyRecords(Schema $schema): void
    {
        if ($schema->hasTable('idempotency_records')) {
            return;
        }

        $table = $schema->createTable('idempotency_records');
        $this->uuid($table, 'record_id');
        $table->addColumn('scope', 'string', ['length' => 120]);
        $table->addColumn('idempotency_key', 'string', ['length' => 160]);
        $table->addColumn('owner_module', 'string', ['length' => 80]);
        $table->addColumn('request_hash', 'string', ['length' => 128, 'notnull' => false]);
        $table->addColumn('status', 'string', ['length' => 40]);
        $table->addColumn('resource_type', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('resource_id', 'string', ['length' => 120, 'notnull' => false]);
        $table->addColumn('response_code', 'integer', ['notnull' => false]);
        $table->addColumn('response_body', 'json', ['notnull' => false]);
        $table->addColumn('locked_until', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('expires_at', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['record_id']);
        $table->addUniqueIndex(['scope', 'idempotency_key'], 'idempotency_records_scope_key_unique');
        $table->addIndex(['status', 'locked_until'], 'idempotency_records_status_lock_idx');
    }

    private function createSystemSettings(Schema $schema): void
    {
        if ($schema->hasTable('system_settings')) {
            return;
        }

        $table = $schema->createTable('system_settings');
        $table->addColumn('module', 'string', ['length' => 80]);
        $table->addColumn('setting_key', 'string', ['length' => 160]);
        $table->addColumn('setting_value', 'text');
        $this->uuid($table, 'updated_by', false);
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->setPrimaryKey(['module', 'setting_key']);
        $table->addIndex(['module', 'updated_at'], 'system_settings_module_updated_idx');
    }

    private function createPlatformJobs(Schema $schema): void
    {
        if ($schema->hasTable('platform_jobs')) {
            return;
        }

        $table = $schema->createTable('platform_jobs');
        $this->uuid($table, 'job_id');
        $table->addColumn('queue', 'string', ['length' => 120]);
        $table->addColumn('job_type', 'string', ['length' => 180]);
        $table->addColumn('payload', 'text');
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'queued']);
        $table->addColumn('attempts', 'integer', ['default' => 0]);
        $table->addColumn('max_attempts', 'integer', ['default' => 3]);
        $table->addColumn('available_at', 'datetime_immutable');
        $table->addColumn('locked_by', 'string', ['length' => 120, 'notnull' => false]);
        $table->addColumn('locked_until', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('last_error', 'text', ['notnull' => false]);
        $table->addColumn('completed_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('failed_at', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['job_id']);
        $table->addIndex(['queue', 'status', 'available_at'], 'platform_jobs_queue_status_available_idx');
        $table->addIndex(['locked_until'], 'platform_jobs_locked_until_idx');
    }
}
