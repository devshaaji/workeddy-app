<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710050000_RenamePaymentCustomerToOrganization extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename payment_records.customer_id to organization_id and align the canonical payment schema with the organization-based billing model.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('payment_records')) {
            return;
        }

        $table = $schema->getTable('payment_records');
        if ($table->hasColumn('customer_id') && !$table->hasColumn('organization_id')) {
            $this->addSql('ALTER TABLE payment_records CHANGE customer_id organization_id INT NOT NULL');
        }

        $table = $schema->getTable('payment_records');
        if ($table->hasIndex('payment_records_customer_idx')) {
            $this->addSql('DROP INDEX payment_records_customer_idx ON payment_records');
        }
        if (!$table->hasIndex('payment_records_organization_idx')) {
            $this->addSql('CREATE INDEX payment_records_organization_idx ON payment_records (organization_id)');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('payment_records')) {
            return;
        }

        $table = $schema->getTable('payment_records');
        if ($table->hasColumn('organization_id') && !$table->hasColumn('customer_id')) {
            $this->addSql('ALTER TABLE payment_records CHANGE organization_id customer_id INT NOT NULL');
        }

        $table = $schema->getTable('payment_records');
        if ($table->hasIndex('payment_records_organization_idx')) {
            $this->addSql('DROP INDEX payment_records_organization_idx ON payment_records');
        }
        if (!$table->hasIndex('payment_records_customer_idx')) {
            $this->addSql('CREATE INDEX payment_records_customer_idx ON payment_records (customer_id)');
        }
    }
}
