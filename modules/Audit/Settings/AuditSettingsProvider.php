<?php

/**
 * Audit module settings provider — declares all Audit-owned setting definitions.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class AuditSettingsProvider implements IModuleSettingsProvider
{
    public function getModuleName(): string
    {
        return 'audit';
    }

    /** @return SettingDefinition[] */
    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: 'retention_days',
                module: 'audit',
                type: SettingType::INTEGER,
                default: 365,
                label: 'Retention Period (days)',
                description: 'Number of days to retain audit log entries before archival/purge.',
                validation: fn($v) => (int) $v >= 30 && (int) $v <= 3650
                    ? true : 'Must be between 30 and 3650 days (10 years).',
            ),
            new SettingDefinition(
                key: 'mask_sensitive_fields',
                module: 'audit',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Mask Sensitive Fields',
                description: 'Whether sensitive data (passwords, tokens) is masked in audit log before_state/after_state.',
            ),
            new SettingDefinition(
                key: 'record_ip_address',
                module: 'audit',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Record IP Address',
                description: 'Whether the client IP address is captured in audit log entries.',
            ),
            new SettingDefinition(
                key: 'store_state_diffs',
                module: 'audit',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Store State Diffs',
                description: 'Whether before/after state snapshots are stored with audit entries.',
            ),
            new SettingDefinition(
                key: 'max_query_results',
                module: 'audit',
                type: SettingType::INTEGER,
                default: 500,
                label: 'Max Query Results',
                description: 'Maximum number of audit entries returned in a single query.',
                validation: fn($v) => (int) $v >= 10 && (int) $v <= 10000
                    ? true : 'Must be between 10 and 10000.',
            ),
        ];
    }
}
