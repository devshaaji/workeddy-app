<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Infrastructure;

use WorkEddy\Modules\Audit\Domain\Contracts\AppendOnlyAuditLogContract;
use WorkEddy\Modules\Audit\Application\DTOs\AuditRecord;
use WorkEddy\Shared\Support\DateFormatter;

final class DbalAuditLog implements AppendOnlyAuditLogContract
{
    public function __construct(private readonly object $connection) {}

    public function append(AuditRecord $record): void
    {
        try {
            $this->connection->insert('audit_logs', [
                'uuid' => $record->auditId,
                'actor_id' => is_numeric($record->actorId) ? (int)$record->actorId : 0,
                'action' => $record->action,
                'entity_type' => $record->entityType,
                'entity_id' => $record->entityId,
                'module' => $record->metadata['module'] ?? 'iam',
                'before_state' => $record->beforeState === null ? null : json_encode($record->beforeState, JSON_THROW_ON_ERROR),
                'after_state' => $record->afterState === null ? null : json_encode($record->afterState, JSON_THROW_ON_ERROR),
                'ip_address' => $record->metadata['ip_address'] ?? null,
                'created_at' => $this->date($record->createdAt),
            ]);
        } catch (\Throwable) {
            // Audit storage is best-effort and must not block the primary write.
        }
    }

    private function date(string $value): string
    {
        try {
            return DateFormatter::fromNaiveDbString($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }
    }
}
