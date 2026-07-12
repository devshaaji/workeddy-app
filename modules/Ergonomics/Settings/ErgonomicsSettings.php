<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Ergonomics\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class ErgonomicsSettings extends ModuleSettings
{
    public const DEFAULT_MODEL = 'default_model';
    public const ALLOW_VIDEO_INPUT = 'allow_video_input';

    protected function moduleName(): string
    {
        return 'ergonomics';
    }

    public function defaultModel(): string
    {
        return $this->getString(self::DEFAULT_MODEL);
    }

    public function allowVideoInput(): bool
    {
        return $this->getBool(self::ALLOW_VIDEO_INPUT);
    }
}
