<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class BillingSettingsProvider implements IModuleSettingsProvider
{
    public function getModuleName(): string
    {
        return 'billing';
    }

    /** @return SettingDefinition[] */
    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: 'default_currency',
                module: 'billing',
                type: SettingType::STRING,
                default: 'USD',
                label: 'Default Currency',
                description: 'The default currency code for new quotations and invoices (e.g. USD, EUR, GBP).',
            ),
            new SettingDefinition(
                key: 'default_tax_rate',
                module: 'billing',
                type: SettingType::INTEGER,
                default: 0,
                label: 'Default Tax Rate (%)',
                description: 'Default tax percentage applied to invoice items.',
                validation: fn($v) => (int) $v >= 0 && (int) $v <= 100
                    ? true : 'Must be between 0 and 100.',
            ),
            new SettingDefinition(
                key: 'quotation_expiry_days',
                module: 'billing',
                type: SettingType::INTEGER,
                default: 30,
                label: 'Quotation Expiry (Days)',
                description: 'Number of days before a new quotation expires.',
                validation: fn($v) => (int) $v >= 1 && (int) $v <= 365
                    ? true : 'Must be between 1 and 365.',
            ),
            new SettingDefinition(
                key: 'invoice_due_days',
                module: 'billing',
                type: SettingType::INTEGER,
                default: 14,
                label: 'Invoice Due (Days)',
                description: 'Number of days before a new invoice is considered overdue.',
                validation: fn($v) => (int) $v >= 1 && (int) $v <= 365
                    ? true : 'Must be between 1 and 365.',
            ),
            new SettingDefinition(
                key: 'org_name',
                module: 'billing',
                type: SettingType::STRING,
                default: '',
                label: 'Organization Name',
                description: 'Company or organization name displayed on invoices and quotations.',
            ),
            new SettingDefinition(
                key: 'org_address',
                module: 'billing',
                type: SettingType::STRING,
                default: '',
                label: 'Organization Address',
                description: 'Full address displayed on invoices and quotations.',
            ),
            new SettingDefinition(
                key: 'org_phone',
                module: 'billing',
                type: SettingType::STRING,
                default: '',
                label: 'Organization Phone',
                description: 'Phone number displayed on invoices and quotations.',
            ),
            new SettingDefinition(
                key: 'org_email',
                module: 'billing',
                type: SettingType::STRING,
                default: '',
                label: 'Organization Email',
                description: 'Email address displayed on invoices and quotations.',
            ),
            new SettingDefinition(
                key: 'org_tax_id',
                module: 'billing',
                type: SettingType::STRING,
                default: '',
                label: 'Organization Tax ID',
                description: 'Tax or VAT ID displayed on invoices and quotations.',
            ),
        ];
    }
}
