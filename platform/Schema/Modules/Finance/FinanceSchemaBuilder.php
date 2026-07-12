<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Finance;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

final class FinanceSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'finance';
    }

    public function tables(): array
    {
        return [
            'finance_income_records',
            'finance_expense_records',
            'finance_payroll_summaries',
        ];
    }

    public function build(Schema $schema): void
    {
        if (!$schema->hasTable('finance_income_records')) {
            $table = $schema->createTable('finance_income_records');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $this->uuid($table, 'uuid');
            $table->addColumn('source_type', 'string', ['length' => 60]);
            $table->addColumn('reference_number', 'string', ['length' => 80]);
            $table->addColumn('category', 'string', ['length' => 80]);
            $this->decimal($table, 'amount', '0.00');
            $table->addColumn('currency', 'string', ['length' => 10, 'default' => 'USD']);
            $table->addColumn('description', 'text');
            $this->timestamps($table);
            $table->addColumn('archived_at', 'datetime', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uuid'], 'finance_income_uuid_unique');
            $table->addIndex(['category'], 'finance_income_category_idx');
        }

        if (!$schema->hasTable('finance_expense_records')) {
            $table = $schema->createTable('finance_expense_records');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $this->uuid($table, 'uuid');
            $table->addColumn('reference_number', 'string', ['length' => 80]);
            $table->addColumn('category', 'string', ['length' => 80]);
            $this->decimal($table, 'amount', '0.00');
            $table->addColumn('currency', 'string', ['length' => 10, 'default' => 'USD']);
            $table->addColumn('description', 'text');
            $this->timestamps($table);
            $table->addColumn('archived_at', 'datetime', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uuid'], 'finance_expense_uuid_unique');
            $table->addIndex(['category'], 'finance_expense_category_idx');
        }

        if (!$schema->hasTable('finance_payroll_summaries')) {
            $table = $schema->createTable('finance_payroll_summaries');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $this->uuid($table, 'uuid');
            $table->addColumn('period_key', 'string', ['length' => 20]);
            $this->decimal($table, 'gross_amount', '0.00');
            $this->decimal($table, 'net_amount', '0.00');
            $table->addColumn('currency', 'string', ['length' => 10, 'default' => 'USD']);
            $table->addColumn('employee_count', 'integer', ['default' => 0]);
            $this->timestamps($table);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['period_key'], 'finance_payroll_period_unique');
            $table->addUniqueIndex(['uuid'], 'finance_payroll_uuid_unique');
        }
    }
}
