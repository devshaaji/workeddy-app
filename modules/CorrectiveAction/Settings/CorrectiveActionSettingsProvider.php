<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class CorrectiveActionSettingsProvider implements IModuleSettingsProvider
{
    public function getModuleName(): string
    {
        return 'corrective_action';
    }

    public function getDefinitions(): array
    {
        return [
            new SettingDefinition('default_due_days', 'corrective_action', SettingType::INTEGER, 30, 'Default Due Days', 'Default corrective action due date offset.', validation: fn($v) => (int) $v > 0 ? true : 'Must be positive.'),
            new SettingDefinition('follow_up_days_after_verification', 'corrective_action', SettingType::INTEGER, 14, 'Follow-up Days', 'Days after verification to schedule follow-up assessment.', validation: fn($v) => (int) $v > 0 ? true : 'Must be positive.'),
            new SettingDefinition('require_evidence_for_completion', 'corrective_action', SettingType::BOOLEAN, true, 'Require Evidence', 'Require evidence before marking action completed.'),
        ];
    }
}
