<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Content;

use Doctrine\DBAL\Schema\Schema;
use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;

final class ContentSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'content';
    }

    public function tables(): array
    {
        return [
            'content_pages',
            'content_page_revisions',
            'content_media',
        ];
    }

    public function build(Schema $schema): void
    {
        $this->createPages($schema);
        $this->createRevisions($schema);
        $this->createMedia($schema);
    }

    private function createPages(Schema $schema): void
    {
        if ($schema->hasTable('content_pages')) {
            return;
        }

        $table = $schema->createTable('content_pages');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('page_key', 'string', ['length' => 180]);
        $table->addColumn('route_path', 'string', ['length' => 255]);
        $table->addColumn('content_type', 'string', ['length' => 64]);
        $table->addColumn('template_key', 'string', ['length' => 120]);
        $table->addColumn('audience', 'string', ['length' => 40]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'draft']);
        $table->addColumn('published_revision_id', 'integer', ['notnull' => false]);
        $table->addColumn('draft_revision_id', 'integer', ['notnull' => false]);
        $table->addColumn('lock_version', 'integer', ['default' => 1]);
        $table->addColumn('created_by', 'integer', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->addColumn('archived_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('archived_by', 'integer', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'content_pages_uuid_unique');
        $table->addUniqueIndex(['page_key'], 'content_pages_key_unique');
        $table->addIndex(['status'], 'content_pages_status_idx');
    }

    private function createRevisions(Schema $schema): void
    {
        if ($schema->hasTable('content_page_revisions')) {
            return;
        }

        $table = $schema->createTable('content_page_revisions');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('page_id', 'integer');
        $table->addColumn('version_number', 'integer');
        $table->addColumn('revision_status', 'string', ['length' => 40]);
        $table->addColumn('title', 'string', ['length' => 255]);
        $table->addColumn('seo_title', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('seo_description', 'text', ['notnull' => false]);
        $table->addColumn('change_summary', 'text', ['notnull' => false]);
        $table->addColumn('content_snapshot', 'text');
        $table->addColumn('created_by', 'integer', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_by', 'integer', ['notnull' => false]);
        $table->addColumn('updated_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('published_by', 'integer', ['notnull' => false]);
        $table->addColumn('published_at', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'content_page_revisions_uuid_unique');
        $table->addUniqueIndex(['page_id', 'version_number'], 'content_page_revisions_page_version_unique');
        $table->addIndex(['page_id', 'revision_status'], 'content_page_revisions_page_status_idx');
        $table->addForeignKeyConstraint('content_pages', ['page_id'], ['id'], ['onDelete' => 'CASCADE'], 'content_page_revisions_page_fk');
    }

    private function createMedia(Schema $schema): void
    {
        if ($schema->hasTable('content_media')) {
            return;
        }

        $table = $schema->createTable('content_media');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $this->uuid($table, 'storage_file_uuid');
        $table->addColumn('original_name', 'string', ['length' => 255]);
        $table->addColumn('mime_type', 'string', ['length' => 120]);
        $table->addColumn('extension', 'string', ['length' => 20, 'notnull' => false]);
        $table->addColumn('size_bytes', 'bigint');
        $table->addColumn('width', 'integer', ['notnull' => false]);
        $table->addColumn('height', 'integer', ['notnull' => false]);
        $table->addColumn('default_alt_text', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('default_caption', 'text', ['notnull' => false]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'active']);
        $table->addColumn('uploaded_by', 'integer', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->addColumn('archived_at', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'content_media_uuid_unique');
        $table->addUniqueIndex(['storage_file_uuid'], 'content_media_storage_file_uuid_unique');
        $table->addIndex(['status'], 'content_media_status_idx');
    }
}
