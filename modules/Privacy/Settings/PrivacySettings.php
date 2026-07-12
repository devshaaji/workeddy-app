<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class PrivacySettings extends ModuleSettings
{
    public const DEFAULT_VIDEO_CONSENT_TEXT_VERSION = 'default_video_consent_text_version';
    public const DEFAULT_RAW_VIDEO_POLICY = 'default_raw_video_policy';
    public const REQUIRE_VIDEO_ACCESS_LOG = 'require_video_access_log';

    protected function moduleName(): string
    {
        return 'privacy';
    }

    public function defaultVideoConsentTextVersion(): string
    {
        return $this->getString(self::DEFAULT_VIDEO_CONSENT_TEXT_VERSION);
    }

    public function defaultRawVideoPolicy(): string
    {
        return $this->getString(self::DEFAULT_RAW_VIDEO_POLICY);
    }

    public function requireVideoAccessLog(): bool
    {
        return $this->getBool(self::REQUIRE_VIDEO_ACCESS_LOG);
    }
}
