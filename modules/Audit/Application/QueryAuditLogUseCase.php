<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Application;

use WorkEddy\Modules\Audit\Application\DTOs\AuditLogEntryDTO;
use WorkEddy\Modules\Audit\Application\DTOs\QueryAuditLogRequest;
use WorkEddy\Modules\Audit\Domain\Contracts\IAuditLogRepository;
use WorkEddy\Modules\Audit\Settings\AuditSettings;

final class QueryAuditLogUseCase
{
    public function __construct(
        private readonly IAuditLogRepository $repository,
        private readonly AuditSettings $auditSettings,
    ) {}

    /**
     * @return AuditLogEntryDTO[]
     */
    public function execute(QueryAuditLogRequest $request): array
    {
        // Retention enforcement removed from the query path.
        // It must be executed via cron/scheduled job instead
        // to avoid unpredictable write load on admin queries.
        $effectiveLimit = min($request->limit, $this->auditSettings->maxQueryResults());

        return $this->repository->search(new QueryAuditLogRequest(
            actorId: $request->actorId,
            module: $request->module,
            action: $request->action,
            entityType: $request->entityType,
            entityId: $request->entityId,
            fromDate: $request->fromDate,
            toDate: $request->toDate,
            limit: $effectiveLimit,
            offset: $request->offset,
        ));
    }

    /** Count matching entries for pagination. */
    public function count(QueryAuditLogRequest $request): int
    {
        return $this->repository->count($request);
    }
}
