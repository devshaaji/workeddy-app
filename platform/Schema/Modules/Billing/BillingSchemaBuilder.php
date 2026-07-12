<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Billing;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

/**
 * Billed party is always an Organization. There is no Customer module in
 * this codebase (no module directory, no repository interface, not
 * registered in bootstrap/modules.php) \u2014 the legacy `customers` /
 * `customer_contacts` / `customer_addresses` tables that still exist in
 * some databases are orphaned and unowned by any module. See
 * docs/modules-map.md \u00a72.10.
 */
final class BillingSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'billing';
    }

    public function tables(): array
    {
        return ['billing_quotations', 'billing_invoices'];
    }

    public function build(Schema $schema): void
    {
        $this->createQuotations($schema);
        $this->createInvoices($schema);
        $this->reconcileInvoiceSubscriptionLinkage($schema);
    }

    private function createQuotations(Schema $schema): void
    {
        if ($schema->hasTable('billing_quotations')) {
            return;
        }

        $table = $schema->createTable('billing_quotations');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('quotation_number', 'string', ['length' => 64]);
        $table->addColumn('organization_id', 'integer');
        $table->addColumn('lead_id', 'integer', ['notnull' => false]);
        $table->addColumn('status', 'string', ['length' => 32, 'default' => 'draft']);
        $table->addColumn('items', 'json', ['notnull' => false]);
        $this->decimal($table, 'subtotal', '0.00', 10, 2);
        $this->decimal($table, 'tax', '0.00', 10, 2);
        $this->decimal($table, 'total', '0.00', 10, 2);
        $table->addColumn('currency', 'string', ['length' => 3, 'default' => 'USD']);
        $table->addColumn('expires_at', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->addColumn('archived_at', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'billing_quotations_uuid_unique');
        $table->addUniqueIndex(['quotation_number'], 'billing_quotations_number_unique');
        $table->addIndex(['organization_id'], 'billing_quotations_organization_idx');
        $table->addIndex(['lead_id'], 'billing_quotations_lead_idx');
        $table->addIndex(['status'], 'billing_quotations_status_idx');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'RESTRICT'], 'billing_quotations_org_fk');
    }

    private function createInvoices(Schema $schema): void
    {
        if ($schema->hasTable('billing_invoices')) {
            return;
        }

        $table = $schema->createTable('billing_invoices');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('invoice_number', 'string', ['length' => 64]);
        $table->addColumn('organization_id', 'integer');
        $table->addColumn('subscription_uuid', 'string', ['length' => 36, 'notnull' => false]);
        $table->addColumn('quotation_id', 'integer', ['notnull' => false]);
        $table->addColumn('status', 'string', ['length' => 32, 'default' => 'unpaid']);
        $table->addColumn('items', 'json', ['notnull' => false]);
        $this->decimal($table, 'subtotal', '0.00', 10, 2);
        $this->decimal($table, 'tax', '0.00', 10, 2);
        $this->decimal($table, 'total', '0.00', 10, 2);
        $this->decimal($table, 'amount_paid', '0.00', 10, 2);
        $table->addColumn('currency', 'string', ['length' => 3, 'default' => 'USD']);
        $table->addColumn('due_date', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->addColumn('archived_at', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'billing_invoices_uuid_unique');
        $table->addUniqueIndex(['invoice_number'], 'billing_invoices_number_unique');
        $table->addIndex(['organization_id'], 'billing_invoices_organization_idx');
        $table->addIndex(['quotation_id'], 'billing_invoices_quotation_idx');
        $table->addIndex(['status'], 'billing_invoices_status_idx');
        $table->addIndex(['subscription_uuid'], 'billing_invoices_subscription_idx');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'RESTRICT'], 'billing_invoices_org_fk');
    }

    /**
     * Idempotent additive reconciliation for `subscription_uuid` on
     * pre-existing `billing_invoices` tables. Renaming `customer_id` \u2192
     * `organization_id` and dropping `billed_party_type` on databases that
     * already ran earlier migrations is handled by the dedicated
     * Version20260708140000_RemoveCustomerConceptFromBilling migration,
     * not here \u2014 declarative schema-diffing must never rename/drop a
     * column that may hold real data.
     */
    private function reconcileInvoiceSubscriptionLinkage(Schema $schema): void
    {
        if (!$schema->hasTable('billing_invoices')) {
            return;
        }

        $table = $schema->getTable('billing_invoices');
        if (!$table->hasColumn('subscription_uuid')) {
            $table->addColumn('subscription_uuid', 'string', ['length' => 36, 'notnull' => false]);
        }
        if (!$table->hasIndex('billing_invoices_subscription_idx')) {
            $table->addIndex(['subscription_uuid'], 'billing_invoices_subscription_idx');
        }
    }
}
