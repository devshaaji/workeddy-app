<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class AssessmentSettingsProvider implements IModuleSettingsProvider
{
    public function getModuleName(): string
    {
        return 'assessment';
    }

    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: AssessmentSettings::REQUIRE_REVIEW_BEFORE_REPORT,
                module: 'assessment',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Require Review Before Report',
                description: 'Whether assessment reports must use a reviewed final score.',
            ),
            new SettingDefinition(
                key: AssessmentSettings::REQUIRE_VIDEO_CONSENT,
                module: 'assessment',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Require Video Consent',
                description: 'Whether video attachment requires a captured consent text version.',
            ),
            new SettingDefinition(
                key: AssessmentSettings::MAX_VIDEO_SIZE_BYTES,
                module: 'assessment',
                type: SettingType::INTEGER,
                default: 524288000,
                label: 'Maximum Assessment Video Size',
                description: 'Maximum accepted video file size in bytes.',
                validation: static fn($value) => (int) $value >= 1048576 && (int) $value <= 5368709120
                    ? true : 'Must be between 1 MiB and 5 GiB.',
            ),
        ];
    }
}
