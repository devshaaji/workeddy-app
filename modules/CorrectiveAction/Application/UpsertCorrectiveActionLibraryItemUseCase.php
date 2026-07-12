<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionLibraryItem;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class UpsertCorrectiveActionLibraryItemUseCase
{
    private const CONTROL_TYPES = ['engineering', 'workstation_redesign', 'tool_redesign', 'lift_assist', 'administrative', 'staffing', 'training', 'follow_up_observation', 'ppe', 'process', 'temporary', 'permanent'];
    private const HIERARCHY_LEVELS = ['elimination', 'substitution', 'engineering', 'administrative', 'ppe'];
    private const PRIORITIES = ['low', 'medium', 'high', 'critical'];
    private const EVIDENCE_TYPES = ['photo', 'video', 'receipt', 'note', 'worker_feedback', 'follow_up_observation', 'document'];

    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
    ) {}

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function execute(UserContext $actor, array $data): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::MANAGE_LIBRARY);

        $uuid = isset($data['uuid']) && trim((string) $data['uuid']) !== ''
            ? UuidSupport::requireValid((string) $data['uuid'], 'uuid')
            : UuidSupport::generate();
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new ValidationException(['title' => 'Title is required.']);
        }

        $item = new CorrectiveActionLibraryItem(
            id: null,
            uuid: $uuid,
            title: $title,
            description: $this->nullableString($data['description'] ?? null),
            reason: $this->nullableString($data['reason'] ?? null),
            controlType: $this->enum($data['controlType'] ?? $data['control_type'] ?? 'permanent', self::CONTROL_TYPES, 'controlType'),
            hierarchyLevel: $this->enum($data['hierarchyLevel'] ?? $data['hierarchy_level'] ?? 'engineering', self::HIERARCHY_LEVELS, 'hierarchyLevel'),
            riskFactor: $this->nullableString($data['riskFactor'] ?? $data['risk_factor'] ?? null),
            taskType: $this->nullableString($data['taskType'] ?? $data['task_type'] ?? null),
            industry: $this->nullableString($data['industry'] ?? null),
            priority: $this->enum($data['priority'] ?? 'medium', self::PRIORITIES, 'priority'),
            dueDays: max(1, (int) ($data['dueDays'] ?? $data['due_days'] ?? 30)),
            evidenceRequired: (bool) ($data['evidenceRequired'] ?? $data['evidence_required'] ?? true),
            evidenceTypes: $this->evidenceTypes($data['evidenceTypes'] ?? $data['evidence_types'] ?? []),
            followUpDays: $this->nullableInt($data['followUpDays'] ?? $data['follow_up_days'] ?? null),
            isActive: (bool) ($data['isActive'] ?? $data['is_active'] ?? true),
        );

        $saved = $this->repository->upsertLibraryItem($item);
        $this->audit->record('corrective_action.library_item.upserted', 'corrective_action_library', $saved->uuid, afterState: $saved->toView(), actorId: (string) $actor->userId, actorType: 'user');

        return $saved->toView();
    }

    /** @param list<string> $allowed */
    private function enum(mixed $value, array $allowed, string $field): string
    {
        $normalized = strtolower(trim((string) $value));
        if (!in_array($normalized, $allowed, true)) {
            throw new ValidationException([$field => 'Invalid value.']);
        }
        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    /** @return list<string> */
    private function evidenceTypes(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $types = [];
        foreach ($value as $item) {
            $normalized = strtolower(trim((string) $item));
            if ($normalized === '') {
                continue;
            }
            if (!in_array($normalized, self::EVIDENCE_TYPES, true)) {
                throw new ValidationException(['evidenceTypes' => 'Invalid evidence type.']);
            }
            $types[] = $normalized;
        }

        return array_values(array_unique($types));
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(1, (int) $value);
    }
}
