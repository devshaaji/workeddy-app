<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Application\DTOs;

final class AuditRecord
{
    /**
     * @param array<string, mixed>|null $beforeState
     * @param array<string, mixed>|null $afterState
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $auditId,
        public readonly string $action,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $createdAt,
        public readonly ?string $actorId = null,
        public readonly ?string $actorType = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?array $beforeState = null,
        public readonly ?array $afterState = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'audit_id' => $this->auditId,
            'actor_id' => $this->actorId,
            'actor_type' => $this->actorType,
            'action' => $this->action,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'before_json' => $this->beforeState,
            'after_json' => $this->afterState,
            'idempotency_key' => $this->idempotencyKey,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
        ];
    }
}
