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
        $defaultFromEmail = $this->envString('MAIL_FROM_ADDRESS', 'no-reply@workeddy.com');
        $defaultFromName = $this->envString('MAIL_FROM_NAME', 'BrowseMX');
        $defaultReplyToEmail = $this->envString('MAIL_REPLY_TO_ADDRESS', '');
        $defaultReplyToName = $this->envString('MAIL_REPLY_TO_NAME', '');
        $providerList = $this->defaultProviderList();
        $activeProviders = $providerList === [] ? [] : ['email' => 'smtp_main'];

        return [
            // --- Sender Identity ---
            new SettingDefinition(
                key: 'default_from_email',
                module: 'notification',
                type: SettingType::STRING,
                default: $defaultFromEmail,
                label: 'Default From Email',
                description: 'The email address used as the sender for all outbound notifications.',
                validation: fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false
                    ? true : 'Must be a valid email address.',
            ),
            new SettingDefinition(
                key: 'default_from_name',
                module: 'notification',
                type: SettingType::STRING,
                default: $defaultFromName,
                label: 'Default From Name',
                description: 'The display name used as the sender for all outbound notifications.',
            ),
            new SettingDefinition(
                key: 'default_reply_to_email',
                module: 'notification',
                type: SettingType::STRING,
                default: $defaultReplyToEmail,
                label: 'Default Reply-To Email',
                description: 'Optional reply-to email address used for outbound email notifications.',
                validation: static fn($v) => $v === '' || filter_var($v, FILTER_VALIDATE_EMAIL) !== false
                    ? true : 'Must be empty or a valid email address.',
            ),
            new SettingDefinition(
                key: 'default_reply_to_name',
                module: 'notification',
                type: SettingType::STRING,
                default: $defaultReplyToName,
                label: 'Default Reply-To Name',
                description: 'Optional display name used for the reply-to address on outbound email notifications.',
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
                default: $providerList,
                label: 'Provider List',
                description: 'List of all configured notification providers and their credentials.',
            ),
            new SettingDefinition(
                key: 'active_provider_per_channel',
                module: 'notification',
                type: SettingType::JSON,
                default: $activeProviders,
                label: 'Active Providers per Channel',
                description: 'Mapping of channel names (sms, whatsapp, email) to active provider keys.',
            ),
        ];
    }

    private function defaultProviderList(): array
    {
        if (!$this->envBool('MAIL_ENABLED', false)) {
            return [];
        }

        $host = $this->envString('MAIL_HOST', '');
        $port = $this->envInt('MAIL_PORT', 0);
        if ($host === '' || $port <= 0) {
            return [];
        }

        return [[
            'key' => 'smtp_main',
            'provider_type' => 'smtp',
            'enabled' => true,
            'channels' => ['email'],
            'priority' => 1,
            'config' => array_filter([
                'host' => $host,
                'port' => $port,
                'user' => $this->envString('MAIL_USERNAME', ''),
                'pass' => $this->envString('MAIL_PASSWORD', ''),
                'encryption' => $this->envString('MAIL_ENCRYPTION', ''),
            ], static fn(mixed $value): bool => $value !== ''),
        ]];
    }

    private function envString(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function envInt(string $key, int $default): int
    {
        $value = $_ENV[$key] ?? getenv($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function envBool(string $key, bool $default): bool
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
