<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Infrastructure;

use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Modules\Audit\Domain\Contracts\AppendOnlyAuditLogContract;
use WorkEddy\Modules\Audit\Application\DTOs\AuditRecord;

use WorkEddy\Shared\Support\UuidSupport;

final class AppendOnlyAuditService implements IAuditService
{
    public function __construct(private readonly AppendOnlyAuditLogContract $log) {}

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
        try {
            $this->log->append(new AuditRecord(
                auditId: UuidSupport::generate(),
                action: $action,
                entityType: $entityType,
                entityId: $entityId,
                createdAt: gmdate(DATE_ATOM),
                actorId: $actorId,
                actorType: $actorType,
                idempotencyKey: $idempotencyKey,
                beforeState: $beforeState,
                afterState: $afterState,
                metadata: $metadata ?? [],
            ));
        } catch (\Throwable) {
            // Audit is a best-effort side effect; it must not block the user flow.
        }
    }
}
