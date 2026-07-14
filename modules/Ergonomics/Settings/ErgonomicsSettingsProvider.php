<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Ergonomics\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class ErgonomicsSettingsProvider implements IModuleSettingsProvider, \WorkEddy\Platform\Settings\ISettingsPageProvider
{
    public function getModuleName(): string
    {
        return 'ergonomics';
    }

    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: ErgonomicsSettings::DEFAULT_MODEL,
                module: 'ergonomics',
                type: SettingType::STRING,
                default: 'reba',
                label: 'Default Ergonomic Model',
                description: 'Default scoring model selected for manual assessment forms.',
                validation: static fn($value) => in_array($value, ['reba', 'rula', 'niosh'], true)
                    ? true : 'Must be reba, rula, or niosh.',
            ),
            new SettingDefinition(
                key: ErgonomicsSettings::ALLOW_VIDEO_INPUT,
                module: 'ergonomics',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Allow Video Input Type',
                description: 'Whether scoring model metadata can advertise video-supported scoring inputs.',
            ),
        ];
    }

    public function getSettingsPageMetadata(): \WorkEddy\Platform\Settings\SettingsPageMetadata
    {
        return new \WorkEddy\Platform\Settings\SettingsPageMetadata(
            module: 'ergonomics',
            label: 'Ergonomics',
            viewPermissions: [\WorkEddy\Modules\Ergonomics\Authorization\ErgonomicsPermissions::VIEW_MODELS],
            editPermissions: [\WorkEddy\Modules\Ergonomics\Authorization\ErgonomicsPermissions::VIEW_MODELS],
            sortOrder: 260,
        );
    }
}
