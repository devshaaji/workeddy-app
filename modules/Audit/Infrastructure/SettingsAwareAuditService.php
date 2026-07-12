<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Infrastructure;

use WorkEddy\Modules\Audit\Settings\AuditSettings;
use WorkEddy\Platform\Audit\IAuditService;

final class SettingsAwareAuditService implements IAuditService
{
    public function __construct(
        private readonly IAuditService $inner,
        private readonly AuditSettings $auditSettings,
    ) {}

    public function record(
        string $action,
        string $entityType,
        string $entityId,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?string $actorId = null,
        ?string $actorType = null,
        ?string $idempotencyKey = null,
        ?array $metadata = [],
    ): void {
        if (!$this->auditSettings->storeStateDiffs()) {
            $beforeState = null;
            $afterState = null;
        } elseif ($this->auditSettings->maskSensitiveFields()) {
            $beforeState = $beforeState !== null ? $this->maskSensitive($beforeState) : null;
            $afterState = $afterState !== null ? $this->maskSensitive($afterState) : null;
        }

        if (!$this->auditSettings->recordIpAddress()) {
            if (isset($metadata['ipAddress'])) {
                unset($metadata['ipAddress']);
            }
            if (isset($metadata['ip_address'])) {
                unset($metadata['ip_address']);
            }
        }

        $this->inner->record(
            $action,
            $entityType,
            $entityId,
            $beforeState,
            $afterState,
            $actorId,
            $actorType,
            $idempotencyKey,
            $metadata
        );
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function maskSensitive(array $state): array
    {
        $masked = [];
        $sensitivePattern = '/password|passwd|pwd|secret|token|key|api[_-]?key|authorization|auth/i';

        foreach ($state as $key => $value) {
            if (is_string($key) && preg_match($sensitivePattern, $key)) {
                $masked[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $masked[$key] = $this->maskSensitive($value);
                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }
}
