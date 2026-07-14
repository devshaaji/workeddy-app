<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class PrivacySettingsProvider implements IModuleSettingsProvider, \WorkEddy\Platform\Settings\ISettingsPageProvider
{
    public function getModuleName(): string
    {
        return 'privacy';
    }

    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: PrivacySettings::DEFAULT_VIDEO_CONSENT_TEXT_VERSION,
                module: 'privacy',
                type: SettingType::STRING,
                default: 'workeddy-video-consent-v1',
                label: 'Default Video Consent Text Version',
                description: 'Consent text version identifier attached to new video consent records.',
            ),
            new SettingDefinition(
                key: PrivacySettings::DEFAULT_RAW_VIDEO_POLICY,
                module: 'privacy',
                type: SettingType::STRING,
                default: 'retain_for_review',
                label: 'Default Raw Video Retention Policy',
                description: 'Default retention policy applied to raw video files.',
                validation: static fn($v) => in_array($v, ['retain_for_review', 'delete_after_processing', 'retain_indefinitely'], true)
                    ? true : 'Must be retain_for_review, delete_after_processing, or retain_indefinitely.',
            ),
            new SettingDefinition(
                key: PrivacySettings::REQUIRE_VIDEO_ACCESS_LOG,
                module: 'privacy',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Require Video Access Log',
                description: 'Whether all video access events must be recorded in the access log.',
            ),
        ];
    }

    public function getSettingsPageMetadata(): \WorkEddy\Platform\Settings\SettingsPageMetadata
    {
        return new \WorkEddy\Platform\Settings\SettingsPageMetadata(
            module: 'privacy',
            label: 'Privacy',
            viewPermissions: [\WorkEddy\Modules\Privacy\Authorization\PrivacyPermissions::RETENTION_MANAGE],
            editPermissions: [\WorkEddy\Modules\Privacy\Authorization\PrivacyPermissions::RETENTION_MANAGE],
            sortOrder: 190,
        );
    }
}
