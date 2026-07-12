<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\SupportTicket;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

final class SupportTicketSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'support_ticket';
    }

    public function tables(): array
    {
        return [
            'support_tickets',
            'support_ticket_comments',
        ];
    }

    public function build(Schema $schema): void
    {
        $this->createSupportTickets($schema);
        $this->createSupportTicketComments($schema);
    }

    private function createSupportTickets(Schema $schema): void
    {
        if ($schema->hasTable('support_tickets')) {
            return;
        }

        $table = $schema->createTable('support_tickets');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('ticket_number', 'string', ['length' => 50]);
        $table->addColumn('customer_id', 'integer', ['notnull' => false]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('email', 'string', ['length' => 255]);
        $table->addColumn('company', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('circuit_id', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('category', 'string', ['length' => 50, 'default' => 'other']);
        $table->addColumn('priority', 'string', ['length' => 20, 'default' => 'low']);
        $table->addColumn('subject', 'string', ['length' => 255]);
        $table->addColumn('details', 'text');
        $table->addColumn('status', 'string', ['length' => 50, 'default' => 'new']);
        $table->addColumn('assigned_to', 'integer', ['notnull' => false]);
        $table->addColumn('escalation_level', 'integer', ['default' => 0]);
        $this->timestamps($table);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'support_tickets_uuid_unique');
        $table->addUniqueIndex(['ticket_number'], 'support_tickets_number_unique');
        $table->addIndex(['customer_id'], 'support_tickets_customer_idx');
        $table->addIndex(['assigned_to'], 'support_tickets_assigned_idx');
        $table->addIndex(['status'], 'support_tickets_status_idx');
    }

    private function createSupportTicketComments(Schema $schema): void
    {
        if ($schema->hasTable('support_ticket_comments')) {
            return;
        }

        $table = $schema->createTable('support_ticket_comments');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('ticket_id', 'integer');
        $table->addColumn('user_id', 'integer', ['notnull' => false]);
        $table->addColumn('author_name', 'string', ['length' => 255]);
        $table->addColumn('comment', 'text');
        $table->addColumn('is_internal', 'boolean', ['default' => false]);
        $this->createdAt($table);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'support_ticket_comments_uuid_unique');
        $table->addIndex(['ticket_id'], 'support_ticket_comments_ticket_idx');
        $table->addForeignKeyConstraint(
            'support_tickets',
            ['ticket_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'support_ticket_comments_ticket_fk'
        );
    }
}
