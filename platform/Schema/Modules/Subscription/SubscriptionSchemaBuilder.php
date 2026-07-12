<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Subscription;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;
use Doctrine\DBAL\Schema\Schema;

final class SubscriptionSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'subscription';
    }

    public function tables(): array
    {
        return [
            'subscription_plans',
            'subscriptions',
            'subscription_usage',
        ];
    }

    public function build(Schema $schema): void
    {
        $this->createPlans($schema);
        $this->createSubscriptions($schema);
        $this->createUsage($schema);
    }

    private function createPlans(Schema $schema): void
    {
        $table = $schema->hasTable('subscription_plans')
            ? $schema->getTable('subscription_plans')
            : $schema->createTable('subscription_plans');

        if (!$table->hasColumn('id')) {
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->setPrimaryKey(['id']);
        }
        if (!$table->hasColumn('code')) {
            $table->addColumn('code', 'string', ['length' => 32]);
        }
        if (!$table->hasColumn('name')) {
            $table->addColumn('name', 'string', ['length' => 64]);
        }
        if (!$table->hasColumn('description')) {
            $table->addColumn('description', 'text', ['notnull' => false]);
        }
        if (!$table->hasColumn('billing_cycle')) {
            $table->addColumn('billing_cycle', 'string', ['length' => 16, 'default' => 'monthly']);
        }
        if (!$table->hasColumn('price')) {
            $this->decimal($table, 'price', '0.00', 10, 2);
        }
        if (!$table->hasColumn('currency')) {
            $table->addColumn('currency', 'string', ['length' => 3, 'default' => 'USD']);
        }
        if (!$table->hasColumn('features')) {
            $table->addColumn('features', 'json');
        }
        if (!$table->hasColumn('is_active')) {
            $table->addColumn('is_active', 'boolean', ['default' => true]);
        }
        if (!$table->hasColumn('display_order')) {
            $table->addColumn('display_order', 'integer', ['notnull' => false]);
        }
        if (!$table->hasColumn('created_at')) {
            $this->timestamps($table);
        }
        if (!$table->hasIndex('subscription_plans_code_unique')) {
            $table->addUniqueIndex(['code'], 'subscription_plans_code_unique');
        }
        if (!$table->hasIndex('subscription_plans_active_idx')) {
            $table->addIndex(['is_active'], 'subscription_plans_active_idx');
        }
    }

    private function createSubscriptions(Schema $schema): void
    {
        $table = $schema->hasTable('subscriptions')
            ? $schema->getTable('subscriptions')
            : $schema->createTable('subscriptions');

        if (!$table->hasColumn('id')) {
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->setPrimaryKey(['id']);
        }
        if (!$table->hasColumn('uuid')) {
            $this->uuid($table, 'uuid');
        }
        if (!$table->hasColumn('organization_id')) {
            $table->addColumn('organization_id', 'integer');
        }
        if (!$table->hasColumn('organization_uuid')) {
            $this->uuid($table, 'organization_uuid');
        }
        if (!$table->hasColumn('plan_code')) {
            $table->addColumn('plan_code', 'string', ['length' => 32]);
        }
        if (!$table->hasColumn('plan_name')) {
            $table->addColumn('plan_name', 'string', ['length' => 64]);
        }
        if (!$table->hasColumn('status')) {
            $table->addColumn('status', 'string', ['length' => 32, 'default' => 'pending_activation']);
        }
        if (!$table->hasColumn('billing_cycle')) {
            $table->addColumn('billing_cycle', 'string', ['length' => 16, 'default' => 'monthly']);
        }
        if (!$table->hasColumn('start_date')) {
            $table->addColumn('start_date', 'datetime_immutable');
        }
        if (!$table->hasColumn('expiry_date')) {
            $table->addColumn('expiry_date', 'datetime_immutable', ['notnull' => false]);
        }
        if (!$table->hasColumn('activated_at')) {
            $table->addColumn('activated_at', 'datetime_immutable', ['notnull' => false]);
        }
        if (!$table->hasColumn('suspended_at')) {
            $table->addColumn('suspended_at', 'datetime_immutable', ['notnull' => false]);
        }
        if (!$table->hasColumn('suspended_reason')) {
            $table->addColumn('suspended_reason', 'text', ['notnull' => false]);
        }
        if (!$table->hasColumn('cancelled_at')) {
            $table->addColumn('cancelled_at', 'datetime_immutable', ['notnull' => false]);
        }
        if (!$table->hasColumn('cancellation_reason')) {
            $table->addColumn('cancellation_reason', 'text', ['notnull' => false]);
        }
        if (!$table->hasColumn('auto_renew')) {
            $table->addColumn('auto_renew', 'boolean', ['default' => true]);
        }
        if (!$table->hasColumn('current_period_start')) {
            $table->addColumn('current_period_start', 'datetime_immutable', ['notnull' => false]);
        }
        if (!$table->hasColumn('current_period_end')) {
            $table->addColumn('current_period_end', 'datetime_immutable', ['notnull' => false]);
        }
        if (!$table->hasColumn('created_at')) {
            $this->timestamps($table);
        }
        if (!$table->hasIndex('subscriptions_uuid_unique')) {
            $table->addUniqueIndex(['uuid'], 'subscriptions_uuid_unique');
        }
        if (!$table->hasIndex('subscriptions_organization_idx')) {
            $table->addIndex(['organization_id'], 'subscriptions_organization_idx');
        }
        if (!$table->hasIndex('subscriptions_status_idx')) {
            $table->addIndex(['status'], 'subscriptions_status_idx');
        }
        if (!$table->hasForeignKey('subscriptions_organization_fk')) {
            $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'RESTRICT'], 'subscriptions_organization_fk');
        }
        if (!$table->hasForeignKey('subscriptions_plan_fk')) {
            $table->addForeignKeyConstraint('subscription_plans', ['plan_code'], ['code'], ['onDelete' => 'RESTRICT'], 'subscriptions_plan_fk');
        }
    }

    private function createUsage(Schema $schema): void
    {
        $table = $schema->hasTable('subscription_usage')
            ? $schema->getTable('subscription_usage')
            : $schema->createTable('subscription_usage');

        if (!$table->hasColumn('id')) {
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->setPrimaryKey(['id']);
        }
        if (!$table->hasColumn('subscription_uuid')) {
            $this->uuid($table, 'subscription_uuid');
        }
        if (!$table->hasColumn('period_start')) {
            $table->addColumn('period_start', 'date_immutable');
        }
        if (!$table->hasColumn('period_end')) {
            $table->addColumn('period_end', 'date_immutable');
        }
        if (!$table->hasColumn('usage_data')) {
            $table->addColumn('usage_data', 'json');
        }
        if (!$table->hasColumn('updated_at')) {
            $table->addColumn('updated_at', 'datetime_immutable');
        }
        if (!$table->hasIndex('subscription_usage_period_unique')) {
            $table->addUniqueIndex(['subscription_uuid', 'period_start'], 'subscription_usage_period_unique');
        }
        if (!$table->hasIndex('subscription_usage_end_idx')) {
            $table->addIndex(['period_end'], 'subscription_usage_end_idx');
        }
        if (!$table->hasForeignKey('subscription_usage_subscription_fk')) {
            $table->addForeignKeyConstraint('subscriptions', ['subscription_uuid'], ['uuid'], ['onDelete' => 'CASCADE'], 'subscription_usage_subscription_fk');
        }
    }
}
