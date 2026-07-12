<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Storage\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class StorageSettingsProvider implements IModuleSettingsProvider
{
    public function getModuleName(): string
    {
        return 'storage';
    }

    /** @return SettingDefinition[] */
    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: 'default_disk',
                module: 'storage',
                type: SettingType::STRING,
                default: 'local',
                label: 'Default Storage Disk',
                description: 'Default Flysystem disk for new uploads.',
                validation: fn($v) => $v === 'local' ? true : 'Only local disk is supported in this build.',
            ),
            new SettingDefinition(
                key: 'default_visibility',
                module: 'storage',
                type: SettingType::STRING,
                default: 'private',
                label: 'Default File Visibility',
                description: 'Default visibility for new uploads.',
                validation: fn($v) => in_array($v, ['private', 'public'], true) ? true : 'Must be private or public.',
            ),
            new SettingDefinition(
                key: 'local_private_root',
                module: 'storage',
                type: SettingType::STRING,
                default: 'storage/app/private',
                label: 'Local Private Storage Root',
                description: 'Project-relative directory used by the local Flysystem adapter.',
                restartRequired: true,
            ),
            new SettingDefinition(
                key: 'max_upload_bytes',
                module: 'storage',
                type: SettingType::INTEGER,
                default: 5242880,
                label: 'Max Upload Size',
                description: 'Maximum upload size in bytes.',
                validation: fn($v) => (int) $v > 0 && (int) $v <= 52428800 ? true : 'Must be between 1 byte and 50 MB.',
            ),
            new SettingDefinition(
                key: 'allowed_extensions',
                module: 'storage',
                type: SettingType::JSON,
                default: ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
                label: 'Allowed Extensions',
                description: 'Allowed file extensions for uploads.',
                validation: fn($v) => is_array($v) && $v !== [] ? true : 'Must be a non-empty array.',
            ),
            new SettingDefinition(
                key: 'allowed_mime_types',
                module: 'storage',
                type: SettingType::JSON,
                default: [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/jpeg',
                    'image/png',
                ],
                label: 'Allowed MIME Types',
                description: 'Allowed MIME types for uploads.',
                validation: fn($v) => is_array($v) && $v !== [] ? true : 'Must be a non-empty array.',
            ),
        ];
    }
}
