<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class AssessmentSettings extends ModuleSettings
{
    public const REQUIRE_REVIEW_BEFORE_REPORT = 'require_review_before_report';
    public const REQUIRE_VIDEO_CONSENT = 'require_video_consent';
    public const MAX_VIDEO_SIZE_BYTES = 'max_video_size_bytes';

    protected function moduleName(): string
    {
        return 'assessment';
    }

    public function requireReviewBeforeReport(): bool
    {
        return $this->getBool(self::REQUIRE_REVIEW_BEFORE_REPORT);
    }

    public function requireVideoConsent(): bool
    {
        return $this->getBool(self::REQUIRE_VIDEO_CONSENT);
    }

    public function maxVideoSizeBytes(): int
    {
        return $this->getInt(self::MAX_VIDEO_SIZE_BYTES);
    }
}
