<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class OrganizationSettings extends ModuleSettings
{
    public const MAX_STRUCTURE_PAGE_SIZE = 'max_structure_page_size';
    public const DEFAULT_STRUCTURE_STATUS = 'default_structure_status';
    public const ALLOW_NESTED_DEPARTMENTS = 'allow_nested_departments';

    protected function moduleName(): string
    {
        return 'organization';
    }

    public function maxStructurePageSize(): int
    {
        return $this->getInt(self::MAX_STRUCTURE_PAGE_SIZE);
    }

    public function defaultStructureStatus(): string
    {
        return $this->getString(self::DEFAULT_STRUCTURE_STATUS);
    }

    public function allowNestedDepartments(): bool
    {
        return $this->getBool(self::ALLOW_NESTED_DEPARTMENTS);
    }
}
