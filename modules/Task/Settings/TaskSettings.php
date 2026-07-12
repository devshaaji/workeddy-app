<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Task\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class TaskSettings extends ModuleSettings
{
    public const MAX_PAGE_SIZE = 'max_page_size';
    public const DEFAULT_STATUS = 'default_status';
    public const REQUIRE_TASK_CODE = 'require_task_code';

    protected function moduleName(): string
    {
        return 'task';
    }

    public function maxPageSize(): int
    {
        return $this->getInt(self::MAX_PAGE_SIZE);
    }

    public function defaultStatus(): string
    {
        return $this->getString(self::DEFAULT_STATUS);
    }

    public function requireTaskCode(): bool
    {
        return $this->getBool(self::REQUIRE_TASK_CODE);
    }
}
