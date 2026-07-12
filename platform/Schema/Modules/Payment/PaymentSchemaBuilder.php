<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Payment;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

final class PaymentSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'payment';
    }

    public function tables(): array
    {
        return ['payment_records'];
    }

    public function build(Schema $schema): void
    {
        $table = $schema->hasTable('payment_records')
            ? $schema->getTable('payment_records')
            : $schema->createTable('payment_records');

        if (!$table->hasColumn('id')) {
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->setPrimaryKey(['id']);
        }
        if (!$table->hasColumn('uuid')) {
            $this->uuid($table, 'uuid');
        }
        if (!$table->hasColumn('transaction_id')) {
            $table->addColumn('transaction_id', 'string', ['length' => 64]);
        }
        if (!$table->hasColumn('invoice_id')) {
            $table->addColumn('invoice_id', 'integer');
        }
        if (!$table->hasColumn('organization_id')) {
            $table->addColumn('organization_id', 'integer');
        }
        if (!$table->hasColumn('amount')) {
            $this->decimal($table, 'amount', null, 10, 2);
        }
        if (!$table->hasColumn('currency')) {
            $table->addColumn('currency', 'string', ['length' => 3, 'default' => 'USD']);
        }
        if (!$table->hasColumn('method')) {
            $table->addColumn('method', 'string', ['length' => 32]);
        }
        if (!$table->hasColumn('status')) {
            $table->addColumn('status', 'string', ['length' => 32, 'default' => 'pending']);
        }
        if (!$table->hasColumn('gateway')) {
            $table->addColumn('gateway', 'string', ['length' => 64, 'notnull' => false]);
        }
        if (!$table->hasColumn('gateway_reference')) {
            $table->addColumn('gateway_reference', 'string', ['length' => 128, 'notnull' => false]);
        }
        if (!$table->hasColumn('gateway_payload')) {
            $table->addColumn('gateway_payload', 'json', ['notnull' => false]);
        }
        if (!$table->hasColumn('notes')) {
            $table->addColumn('notes', 'text', ['notnull' => false]);
        }
        if (!$table->hasColumn('payment_date')) {
            $table->addColumn('payment_date', 'datetime_immutable');
        }
        if (!$table->hasColumn('created_at')) {
            $this->timestamps($table);
        }
        if (!$table->hasIndex('payment_records_uuid_unique')) {
            $table->addUniqueIndex(['uuid'], 'payment_records_uuid_unique');
        }
        if (!$table->hasIndex('payment_records_transaction_unique')) {
            $table->addUniqueIndex(['transaction_id'], 'payment_records_transaction_unique');
        }
        if (!$table->hasIndex('payment_records_invoice_idx')) {
            $table->addIndex(['invoice_id'], 'payment_records_invoice_idx');
        }
        if (!$table->hasIndex('payment_records_organization_idx')) {
            $table->addIndex(['organization_id'], 'payment_records_organization_idx');
        }
        if (!$table->hasIndex('payment_records_status_idx')) {
            $table->addIndex(['status'], 'payment_records_status_idx');
        }
        if (!$table->hasIndex('payment_records_gateway_idx')) {
            $table->addIndex(['gateway'], 'payment_records_gateway_idx');
        }
    }
}
