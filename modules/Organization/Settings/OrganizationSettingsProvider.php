<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class OrganizationSettingsProvider implements IModuleSettingsProvider
{
    public function getModuleName(): string
    {
        return 'organization';
    }

    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: 'max_structure_page_size',
                module: 'organization',
                type: SettingType::INTEGER,
                default: 100,
                label: 'Maximum Structure Page Size',
                description: 'Upper bound for worksite, department, and job role listing page sizes.',
                validation: static fn($value) => (int) $value >= 10 && (int) $value <= 500
                    ? true : 'Must be between 10 and 500.',
            ),
            new SettingDefinition(
                key: 'default_structure_status',
                module: 'organization',
                type: SettingType::STRING,
                default: 'active',
                label: 'Default Structure Status',
                description: 'Default status assigned to newly created worksites, departments, and job roles.',
                validation: static fn($value) => in_array($value, ['active', 'inactive'], true)
                    ? true : 'Must be active or inactive.',
            ),
            new SettingDefinition(
                key: 'allow_nested_departments',
                module: 'organization',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Allow Nested Departments',
                description: 'Whether departments may reference parent departments within the same organization.',
            ),
        ];
    }
}
