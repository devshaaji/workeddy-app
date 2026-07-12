<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Application\DTOs;

final class AuditLogEntryDTO
{
    public readonly string $id;
    public readonly int|string $actorId;
    public readonly ?string $actorName;
    public readonly ?string $actorUsername;
    public readonly string $actorLabel;
    public readonly string $action;
    public readonly string $entityType;
    public readonly string $entityId;
    public readonly string $module;
    public readonly ?array $before;
    public readonly ?array $after;
    public readonly ?string $ipAddress;
    public readonly string $createdAt;

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        string $id,
        int|string $actorId,
        string $action,
        string $entityType,
        string $entityId,
        string $module,
        ?array $before,
        ?array $after,
        ?string $ipAddress,
        string $createdAt,
        ?string $actorName = null,
        ?string $actorUsername = null,
        ?string $actorLabel = null,
    ) {
        $this->id = $id;
        $this->actorId = $actorId;
        $this->actorName = $this->nullableTrimmed($actorName);
        $this->actorUsername = $this->nullableTrimmed($actorUsername);
        $this->actorLabel = $this->nullableTrimmed($actorLabel)
            ?? $this->actorName
            ?? $this->actorUsername
            ?? ($actorId === 0 ? 'System' : 'Unknown user');
        $this->action = $action;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->module = $module;
        $this->before = $before;
        $this->after = $after;
        $this->ipAddress = $ipAddress;
        $this->createdAt = $createdAt;
    }

    private function nullableTrimmed(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }
}
