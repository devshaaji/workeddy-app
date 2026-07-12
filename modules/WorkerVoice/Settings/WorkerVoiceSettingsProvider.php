<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class WorkerVoiceSettingsProvider implements IModuleSettingsProvider
{
    public function getModuleName(): string
    {
        return 'worker_voice';
    }

    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: WorkerVoiceSettings::BODY_REGIONS,
                module: 'worker_voice',
                type: SettingType::JSON,
                default: [
                    ['key' => 'neck', 'label' => 'Neck'],
                    ['key' => 'shoulders', 'label' => 'Shoulders'],
                    ['key' => 'upper_back', 'label' => 'Upper Back'],
                    ['key' => 'lower_back', 'label' => 'Lower Back'],
                    ['key' => 'elbows', 'label' => 'Elbows'],
                    ['key' => 'wrists_hands', 'label' => 'Wrists & Hands'],
                    ['key' => 'hips', 'label' => 'Hips'],
                    ['key' => 'knees', 'label' => 'Knees'],
                    ['key' => 'ankles_feet', 'label' => 'Ankles & Feet'],
                ],
                label: 'Body Regions',
                description: 'List of body regions available for worker discomfort reporting.',
            ),
            new SettingDefinition(
                key: WorkerVoiceSettings::QUESTION_CATALOG,
                module: 'worker_voice',
                type: SettingType::JSON,
                default: [
                    ['key' => 'hasDiscomfort', 'type' => 'boolean', 'label' => 'Did you feel discomfort during this task?', 'required' => true],
                    ['key' => 'discomfortLevel', 'type' => 'scale', 'label' => 'How strong was the discomfort?', 'scaleMin' => 0, 'scaleMax' => 5, 'required' => true],
                    ['key' => 'frequencyLevel', 'type' => 'scale', 'label' => 'How often does this happen?', 'scaleMin' => 0, 'scaleMax' => 5, 'required' => true],
                    ['key' => 'difficultyLevel', 'type' => 'scale', 'label' => 'How hard does this make the task?', 'scaleMin' => 0, 'scaleMax' => 5, 'required' => true],
                    ['key' => 'reportingComfortLevel', 'type' => 'scale', 'label' => 'How comfortable do you feel reporting this?', 'scaleMin' => 0, 'scaleMax' => 5, 'required' => true],
                    ['key' => 'pain7DayLevel', 'type' => 'scale', 'label' => 'How much pain did you feel in the last 7 days?', 'scaleMin' => 0, 'scaleMax' => 5, 'required' => true],
                    ['key' => 'pain30DayLevel', 'type' => 'scale', 'label' => 'How much pain did you feel in the last 30 days?', 'scaleMin' => 0, 'scaleMax' => 5, 'required' => true],
                    ['key' => 'suggestedChange', 'type' => 'textarea', 'label' => 'What should change to make this task easier or safer?', 'required' => false],
                ],
                label: 'Question Catalog',
                description: 'Ordered list of survey questions presented to workers during discomfort reporting.',
            ),
            new SettingDefinition(
                key: WorkerVoiceSettings::MAX_SUGGESTED_CHANGE_LENGTH,
                module: 'worker_voice',
                type: SettingType::INTEGER,
                default: 500,
                label: 'Max Suggested Change Length',
                description: 'Maximum character length for the suggested change free-text field.',
                validation: static fn($v) => (int) $v >= 100 && (int) $v <= 5000
                    ? true : 'Must be between 100 and 5000.',
            ),
            new SettingDefinition(
                key: WorkerVoiceSettings::MAX_TREND_SAMPLE_SIZE,
                module: 'worker_voice',
                type: SettingType::INTEGER,
                default: 5000,
                label: 'Max Trend Sample Size',
                description: 'Maximum number of responses included in trend analysis queries.',
                validation: static fn($v) => (int) $v >= 100 && (int) $v <= 100000
                    ? true : 'Must be between 100 and 100000.',
            ),
            new SettingDefinition(
                key: WorkerVoiceSettings::REQUIRE_TASK_OR_ASSESSMENT,
                module: 'worker_voice',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Require Task or Assessment',
                description: 'Whether a worker voice submission must be linked to a task or assessment.',
            ),
        ];
    }
}
