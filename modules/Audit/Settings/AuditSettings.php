<?php

/**
 * Audit module settings — typed accessors for Audit runtime configuration.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class AuditSettings extends ModuleSettings
{
    protected function moduleName(): string
    {
        return 'audit';
    }

    /** Audit log retention period in days. */
    public function retentionDays(): int
    {
        return $this->getInt('retention_days');
    }

    /** Whether sensitive fields are masked in audit log entries. */
    public function maskSensitiveFields(): bool
    {
        return $this->getBool('mask_sensitive_fields');
    }

    /** Whether IP address is recorded in audit entries. */
    public function recordIpAddress(): bool
    {
        return $this->getBool('record_ip_address');
    }

    /** Whether before/after state diffs are stored. */
    public function storeStateDiffs(): bool
    {
        return $this->getBool('store_state_diffs');
    }

    /** Maximum number of audit log entries returned per query. */
    public function maxQueryResults(): int
    {
        return $this->getInt('max_query_results');
    }
}
