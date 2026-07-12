<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Application\DTOs;

final class RecordAuditTrailRequest
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        public readonly int $actorId,
        public readonly string $action,
        public readonly string $entityType,
        public readonly string|int $entityId,
        public readonly string $module,
        public readonly ?array $before = null,
        public readonly ?array $after = null,
        public readonly ?string $ipAddress = null,
    ) {}
}
