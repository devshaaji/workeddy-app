<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Audit;

interface IAuditService
{
    /**
     * @param array<string, mixed>|null $beforeState
     * @param array<string, mixed>|null $afterState
     */
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
    ): void;
}
