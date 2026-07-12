<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Application;

use WorkEddy\Modules\Audit\Application\DTOs\RecordAuditTrailRequest;
use WorkEddy\Modules\Audit\Settings\AuditSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Shared\Exceptions\ValidationException;

final class RecordAuditTrailUseCase
{
    public function __construct(
        private readonly IAuditService $auditService,
        private readonly AuditSettings $auditSettings,
    ) {}

    public function execute(RecordAuditTrailRequest $request): void
    {
        $errors = [];

        if ($request->actorId < 0) {
            $errors['actorId'] = 'actorId must be zero or positive.';
        }

        if (trim($request->action) === '') {
            $errors['action'] = 'action is required.';
        }

        if (trim($request->entityType) === '') {
            $errors['entityType'] = 'entityType is required.';
        }

        if ((string) $request->entityId === '') {
            $errors['entityId'] = 'entityId is required.';
        }

        if (trim($request->module) === '') {
            $errors['module'] = 'module is required.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $beforeState = $request->before;
        $afterState = $request->after;

        if (!$this->auditSettings->storeStateDiffs()) {
            $beforeState = null;
            $afterState = null;
        } elseif ($this->auditSettings->maskSensitiveFields()) {
            $beforeState = $beforeState !== null ? $this->maskSensitive($beforeState) : null;
            $afterState = $afterState !== null ? $this->maskSensitive($afterState) : null;
        }

        $ipAddress = $this->auditSettings->recordIpAddress()
            ? $request->ipAddress
            : null;

        $this->auditService->record(
            action: $request->action,
            entityType: $request->entityType,
            entityId: (string) $request->entityId,
            beforeState: $beforeState,
            afterState: $afterState,
            actorId: (string) $request->actorId,
            actorType: 'User',
            metadata: [
                'module' => $request->module,
                'ipAddress' => $ipAddress,
            ],
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
