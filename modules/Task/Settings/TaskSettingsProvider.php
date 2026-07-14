<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Task\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class TaskSettingsProvider implements IModuleSettingsProvider, \WorkEddy\Platform\Settings\ISettingsPageProvider
{
    public function getModuleName(): string
    {
        return 'task';
    }

    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: 'max_page_size',
                module: 'task',
                type: SettingType::INTEGER,
                default: 100,
                label: 'Maximum Task Page Size',
                description: 'Upper bound for organization task listing page sizes.',
                validation: static fn($value) => (int) $value >= 10 && (int) $value <= 500
                    ? true : 'Must be between 10 and 500.',
            ),
            new SettingDefinition(
                key: 'default_status',
                module: 'task',
                type: SettingType::STRING,
                default: 'active',
                label: 'Default Task Status',
                description: 'Default status assigned to newly created task records.',
                validation: static fn($value) => in_array($value, ['active', 'inactive'], true)
                    ? true : 'Must be active or inactive.',
            ),
            new SettingDefinition(
                key: 'require_task_code',
                module: 'task',
                type: SettingType::BOOLEAN,
                default: false,
                label: 'Require Task Code',
                description: 'Whether organization task creation requires a human-assigned task code.',
            ),
        ];
    }

    public function getSettingsPageMetadata(): \WorkEddy\Platform\Settings\SettingsPageMetadata
    {
        return new \WorkEddy\Platform\Settings\SettingsPageMetadata(
            module: 'task',
            label: 'Task',
            viewPermissions: [\WorkEddy\Modules\Task\Authorization\TaskPermissions::UPDATE],
            editPermissions: [\WorkEddy\Modules\Task\Authorization\TaskPermissions::UPDATE],
            sortOrder: 230,
        );
    }
}
