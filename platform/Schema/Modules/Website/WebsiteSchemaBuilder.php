<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Website;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

final class WebsiteSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'website';
    }

    public function tables(): array
    {
        return ['website_blog_posts', 'website_tickets'];
    }

    public function build(Schema $schema): void
    {
        $this->createBlogPosts($schema);
        $this->createTickets($schema);
    }

    private function createBlogPosts(Schema $schema): void
    {
        if ($schema->hasTable('website_blog_posts')) {
            return;
        }

        $table = $schema->createTable('website_blog_posts');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('title', 'string', ['length' => 255]);
        $table->addColumn('slug', 'string', ['length' => 255]);
        $table->addColumn('content', 'text');
        $table->addColumn('excerpt', 'text', ['notnull' => false]);
        $table->addColumn('author_id', 'integer', ['notnull' => false]);
        $table->addColumn('status', 'string', ['length' => 50, 'default' => 'draft']);
        $table->addColumn('published_at', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'website_blog_posts_uuid_unique');
        $table->addUniqueIndex(['slug'], 'website_blog_posts_slug_unique');
        $table->addIndex(['status'], 'website_blog_posts_status_idx');
        $table->addIndex(['created_at'], 'website_blog_posts_created_at_idx');
    }

    private function createTickets(Schema $schema): void
    {
        if ($schema->hasTable('website_tickets')) {
            return;
        }

        $table = $schema->createTable('website_tickets');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('ticket_number', 'string', ['length' => 50]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('email', 'string', ['length' => 255]);
        $table->addColumn('company', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('circuit_id', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('priority', 'string', ['length' => 20, 'default' => 'p3']);
        $table->addColumn('category', 'string', ['length' => 50, 'default' => 'other']);
        $table->addColumn('subject', 'string', ['length' => 255]);
        $table->addColumn('details', 'text');
        $table->addColumn('status', 'string', ['length' => 50, 'default' => 'new']);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['ticket_number'], 'website_tickets_number_unique');
        $table->addIndex(['email'], 'website_tickets_email_idx');
        $table->addIndex(['status', 'created_at'], 'website_tickets_status_created_idx');
    }
}
