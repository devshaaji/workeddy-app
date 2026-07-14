<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class ExportSettingsProvider implements IModuleSettingsProvider, \WorkEddy\Platform\Settings\ISettingsPageProvider
{
    public function getModuleName(): string
    {
        return 'export';
    }

    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: ExportSettings::ALLOWED_FORMATS,
                module: 'export',
                type: SettingType::JSON,
                default: ['csv', 'xlsx'],
                label: 'Allowed Formats',
                description: 'Research export file formats available to users.',
            ),
            new SettingDefinition(
                key: ExportSettings::DEFAULT_FORMAT,
                module: 'export',
                type: SettingType::STRING,
                default: 'csv',
                label: 'Default Format',
                description: 'Default format selected for research export generation.',
            ),
            new SettingDefinition(
                key: ExportSettings::SIGNED_LINK_TTL_MINUTES,
                module: 'export',
                type: SettingType::INTEGER,
                default: 15,
                label: 'Signed Link TTL Minutes',
                description: 'How long signed research export download links remain valid.',
            ),
            new SettingDefinition(
                key: ExportSettings::MAX_EXPORT_ROWS,
                module: 'export',
                type: SettingType::INTEGER,
                default: 50000,
                label: 'Max Export Rows',
                description: 'Maximum number of rows allowed in a single research export.',
            ),
            new SettingDefinition(
                key: ExportSettings::DEIDENTIFICATION_PROFILE,
                module: 'export',
                type: SettingType::STRING,
                default: 'research_default_v1',
                label: 'De-identification Profile',
                description: 'Current named de-identification profile applied to research exports.',
            ),
        ];
    }

    public function getSettingsPageMetadata(): \WorkEddy\Platform\Settings\SettingsPageMetadata
    {
        return new \WorkEddy\Platform\Settings\SettingsPageMetadata(
            module: 'export',
            label: 'Export',
            viewPermissions: [\WorkEddy\Modules\Export\Authorization\ExportPermissions::GENERATE],
            editPermissions: [\WorkEddy\Modules\Export\Authorization\ExportPermissions::GENERATE],
            sortOrder: 270,
        );
    }
}
