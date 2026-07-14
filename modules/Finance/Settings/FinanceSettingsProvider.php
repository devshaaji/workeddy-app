<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class FinanceSettingsProvider implements IModuleSettingsProvider, \WorkEddy\Platform\Settings\ISettingsPageProvider
{
    public function getModuleName(): string
    {
        return 'finance';
    }

    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: 'default_expense_currency',
                module: 'finance',
                type: SettingType::STRING,
                default: 'USD',
                label: 'Default Expense Currency',
                description: 'Default currency used when recording manual expenses.',
            ),
            new SettingDefinition(
                key: 'payroll_summary_enabled',
                module: 'finance',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Payroll Summary Enabled',
                description: 'Enable payroll summary reporting in finance dashboards.',
            ),
        ];
    }

    public function getSettingsPageMetadata(): \WorkEddy\Platform\Settings\SettingsPageMetadata
    {
        return new \WorkEddy\Platform\Settings\SettingsPageMetadata(
            module: 'finance',
            label: 'Finance',
            viewPermissions: [\WorkEddy\Modules\Finance\Authorization\FinancePermissions::SETTINGS],
            editPermissions: [\WorkEddy\Modules\Finance\Authorization\FinancePermissions::SETTINGS],
            sortOrder: 140,
        );
    }
}
