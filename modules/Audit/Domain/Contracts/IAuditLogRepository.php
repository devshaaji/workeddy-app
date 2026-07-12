<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Domain\Contracts;

use WorkEddy\Modules\Audit\Application\DTOs\AuditLogEntryDTO;
use WorkEddy\Modules\Audit\Application\DTOs\QueryAuditLogRequest;

interface IAuditLogRepository
{
    public function findByUuid(string $uuid): ?AuditLogEntryDTO;

    /**
     * @return AuditLogEntryDTO[]
     */
    public function search(QueryAuditLogRequest $request): array;

    /** Count matching audit log entries for pagination. */
    public function count(QueryAuditLogRequest $request): int;

    public function purgeOlderThan(string $cutoffDateTime): int;
}
