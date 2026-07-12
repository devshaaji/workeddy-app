<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Application\DTOs;

use WorkEddy\Shared\Exceptions\ValidationException;

final class QueryAuditLogRequest
{
    public function __construct(
        public readonly int|string|null $actorId = null,
        public readonly ?string $module = null,
        public readonly ?string $action = null,
        public readonly ?string $entityType = null,
        public readonly ?string $entityId = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly int $limit = 200,
        public readonly int $offset = 0,
    ) {
        $errors = [];

        if ($this->actorId !== null && is_int($this->actorId) && $this->actorId < 0) {
            $errors['actorId'] = 'actorId must be zero or positive.';
        }

        if ($this->module !== null && strlen(trim($this->module)) > 50) {
            $errors['module'] = 'module must not exceed 50 characters.';
        }

        if ($this->action !== null && strlen(trim($this->action)) > 100) {
            $errors['action'] = 'action must not exceed 100 characters.';
        }

        if ($this->entityType !== null && strlen(trim($this->entityType)) > 50) {
            $errors['entityType'] = 'entityType must not exceed 50 characters.';
        }

        if ($this->entityId !== null && strlen(trim($this->entityId)) > 255) {
            $errors['entityId'] = 'entityId must not exceed 255 characters.';
        }

        if ($this->fromDate !== null && !$this->isValidDateTime($this->fromDate)) {
            $errors['fromDate'] = 'fromDate must be a valid date-time string.';
        }

        if ($this->toDate !== null && !$this->isValidDateTime($this->toDate)) {
            $errors['toDate'] = 'toDate must be a valid date-time string.';
        }

        if ($this->limit < 1 || $this->limit > 10000) {
            $errors['limit'] = 'limit must be between 1 and 10000.';
        }

        if ($this->offset < 0) {
            $errors['offset'] = 'offset must be zero or positive.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    private function isValidDateTime(string $value): bool
    {
        return strtotime($value) !== false;
    }
}
