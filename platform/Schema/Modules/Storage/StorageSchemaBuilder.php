<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Storage;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

final class StorageSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'storage';
    }

    public function tables(): array
    {
        return ['uploads'];
    }

    public function build(Schema $schema): void
    {
        if ($schema->hasTable('uploads')) {
            return;
        }

        $table = $schema->createTable('uploads');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('disk', 'string', ['length' => 80]);
        $table->addColumn('visibility', 'string', ['length' => 40]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'active']);
        $table->addColumn('path', 'string', ['length' => 1024]);
        $table->addColumn('owner_type', 'string', ['length' => 120]);
        $this->uuid($table, 'owner_uuid');
        $table->addColumn('field_name', 'string', ['length' => 120]);
        $table->addColumn('original_name', 'string', ['length' => 255]);
        $table->addColumn('mime_type', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('extension', 'string', ['length' => 32, 'notnull' => false]);
        $table->addColumn('size_bytes', 'bigint', ['default' => 0]);
        $table->addColumn('width', 'integer', ['notnull' => false]);
        $table->addColumn('height', 'integer', ['notnull' => false]);
        $table->addColumn('checksum_sha256', 'string', ['length' => 64, 'notnull' => false]);
        $table->addColumn('uploaded_by', 'integer', ['notnull' => false]);
        $table->addColumn('deletion_requested_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('deletion_requested_by', 'integer', ['notnull' => false]);
        $this->createdAt($table);
        $table->addColumn('updated_at', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'uploads_uuid_unique');
        $table->addIndex(['owner_type', 'owner_uuid'], 'uploads_owner_idx');
        $table->addIndex(['status', 'created_at'], 'uploads_status_created_idx');
        $table->addIndex(['disk', 'visibility'], 'uploads_disk_visibility_idx');
        $table->addIndex(['uploaded_by'], 'uploads_uploaded_by_idx');
    }
}
