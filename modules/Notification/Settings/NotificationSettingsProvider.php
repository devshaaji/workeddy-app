<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class NotificationSettingsProvider implements IModuleSettingsProvider
{
    public function getModuleName(): string
    {
        return 'notification';
    }

    /** @return SettingDefinition[] */
    public function getDefinitions(): array
    {
        return [
            // --- Sender Identity ---
            new SettingDefinition(
                key: 'default_from_email',
                module: 'notification',
                type: SettingType::STRING,
                default: 'noreply@browsemx.local',
                label: 'Default From Email',
                description: 'The email address used as the sender for all outbound notifications.',
                validation: fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false
                    ? true : 'Must be a valid email address.',
            ),
            new SettingDefinition(
                key: 'default_from_name',
                module: 'notification',
                type: SettingType::STRING,
                default: 'BrowseMX',
                label: 'Default From Name',
                description: 'The display name used as the sender for all outbound notifications.',
            ),

            // --- Delivery ---
            new SettingDefinition(
                key: 'queue_enabled',
                module: 'notification',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Queue Notifications',
                description: 'Whether notifications are dispatched via the platform queue (true) or sent inline (false).',
            ),
            new SettingDefinition(
                key: 'http_timeout_seconds',
                module: 'notification',
                type: SettingType::INTEGER,
                default: 10,
                label: 'HTTP Timeout',
                description: 'Maximum time in seconds to wait for a provider response.',
            ),
            new SettingDefinition(
                key: 'http_connect_timeout_seconds',
                module: 'notification',
                type: SettingType::INTEGER,
                default: 5,
                label: 'HTTP Connect Timeout',
                description: 'Maximum time in seconds to wait when establishing a provider connection.',
            ),

            // --- Fallback & Retry Settings ---
            new SettingDefinition(
                key: 'retry_max_attempts',
                module: 'notification',
                type: SettingType::INTEGER,
                default: 3,
                label: 'Max Retry Attempts',
                description: 'Maximum number of retries for temporary provider failures.',
            ),
            new SettingDefinition(
                key: 'retry_delay_seconds',
                module: 'notification',
                type: SettingType::INTEGER,
                default: 60,
                label: 'Retry Delay',
                description: 'Delay in seconds between retry attempts.',
            ),
            new SettingDefinition(
                key: 'fallback_enabled',
                module: 'notification',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Enable Fallback',
                description: 'Whether to fallback to alternative channels when the preferred channel fails.',
            ),

            // --- Dynamic Provider Registry ---
            new SettingDefinition(
                key: 'provider_list',
                module: 'notification',
                type: SettingType::JSON,
                default: [],
                label: 'Provider List',
                description: 'List of all configured notification providers and their credentials.',
            ),
            new SettingDefinition(
                key: 'active_provider_per_channel',
                module: 'notification',
                type: SettingType::JSON,
                default: [],
                label: 'Active Providers per Channel',
                description: 'Mapping of channel names (sms, whatsapp, email) to active provider keys.',
            ),
        ];
    }
}
