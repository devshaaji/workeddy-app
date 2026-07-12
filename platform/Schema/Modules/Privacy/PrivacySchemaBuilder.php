<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Privacy;

use Doctrine\DBAL\Schema\Schema;
use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;

final class PrivacySchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'privacy';
    }

    public function tables(): array
    {
        return ['privacy_video_consents', 'privacy_video_access_logs', 'privacy_retention_policies'];
    }

    public function build(Schema $schema): void
    {
        $this->buildConsents($schema);
        $this->buildAccessLogs($schema);
        $this->buildRetentionPolicies($schema);
    }

    private function buildConsents(Schema $schema): void
    {
        if ($schema->hasTable('privacy_video_consents')) {
            return;
        }

        $table = $schema->createTable('privacy_video_consents');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $this->uuid($table, 'organization_uuid');
        $this->uuid($table, 'assessment_uuid');
        $this->uuid($table, 'storage_file_uuid');
        $table->addColumn('user_id', 'integer');
        $table->addColumn('text_version', 'string', ['length' => 120]);
        $table->addColumn('accepted_notice', 'boolean', ['default' => true]);
        $table->addColumn('ip_address', 'string', ['length' => 80, 'notnull' => false]);
        $table->addColumn('user_agent', 'string', ['length' => 512, 'notnull' => false]);
        $table->addColumn('accepted_at', 'datetime_immutable');
        $this->createdAt($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'privacy_video_consents_uuid_unique');
        $table->addIndex(['assessment_uuid', 'storage_file_uuid'], 'privacy_video_consents_assessment_file_idx');
    }

    private function buildAccessLogs(Schema $schema): void
    {
        if ($schema->hasTable('privacy_video_access_logs')) {
            return;
        }

        $table = $schema->createTable('privacy_video_access_logs');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $this->uuid($table, 'organization_uuid');
        $this->uuid($table, 'assessment_uuid');
        $this->uuid($table, 'storage_file_uuid');
        $table->addColumn('user_id', 'integer');
        $table->addColumn('purpose', 'string', ['length' => 120]);
        $table->addColumn('ip_address', 'string', ['length' => 80, 'notnull' => false]);
        $table->addColumn('user_agent', 'string', ['length' => 512, 'notnull' => false]);
        $table->addColumn('accessed_at', 'datetime_immutable');
        $this->createdAt($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'privacy_video_access_logs_uuid_unique');
        $table->addIndex(['assessment_uuid', 'storage_file_uuid'], 'privacy_video_access_logs_assessment_file_idx');
        $table->addIndex(['user_id', 'accessed_at'], 'privacy_video_access_logs_user_time_idx');
    }

    private function buildRetentionPolicies(Schema $schema): void
    {
        if ($schema->hasTable('privacy_retention_policies')) {
            return;
        }

        $table = $schema->createTable('privacy_retention_policies');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('organization_id', 'integer');
        $this->uuid($table, 'organization_uuid');
        $table->addColumn('raw_video_policy', 'string', ['length' => 80]);
        $table->addColumn('retain_screenshots_only', 'boolean', ['default' => false]);
        $table->addColumn('retain_for_pilot_evidence', 'boolean', ['default' => false]);
        $table->addColumn('retention_days', 'integer', ['default' => 0]);
        $table->addColumn('updated_by', 'integer');
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['organization_id'], 'privacy_retention_policies_org_unique');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'privacy_retention_policies_org_fk');
    }
}
