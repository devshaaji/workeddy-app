<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class WorkerVoiceSettings extends ModuleSettings
{
    public const BODY_REGIONS = 'body_regions';
    public const QUESTION_CATALOG = 'question_catalog';
    public const MAX_SUGGESTED_CHANGE_LENGTH = 'max_suggested_change_length';
    public const MAX_TREND_SAMPLE_SIZE = 'max_trend_sample_size';
    public const REQUIRE_TASK_OR_ASSESSMENT = 'require_task_or_assessment';

    protected function moduleName(): string
    {
        return 'worker_voice';
    }

    /** @return list<array<string, mixed>> */
    public function bodyRegions(): array
    {
        return array_values($this->getJson(self::BODY_REGIONS));
    }

    /** @return list<string> */
    public function bodyRegionKeys(): array
    {
        return array_map(static fn(array $region): string => (string) ($region['key'] ?? ''), $this->bodyRegions());
    }

    /** @return list<array<string, mixed>> */
    public function questionCatalog(): array
    {
        return array_values($this->getJson(self::QUESTION_CATALOG));
    }

    public function maxSuggestedChangeLength(): int
    {
        return $this->getInt(self::MAX_SUGGESTED_CHANGE_LENGTH);
    }

    public function maxTrendSampleSize(): int
    {
        return $this->getInt(self::MAX_TREND_SAMPLE_SIZE);
    }

    public function requireTaskOrAssessment(): bool
    {
        return $this->getBool(self::REQUIRE_TASK_OR_ASSESSMENT);
    }
}
