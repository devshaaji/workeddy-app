<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class ReviewRecommendationUseCase
{
    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
    ) {}

    /** @param array<string, mixed> $edits @return array<string, mixed> */
    public function accept(string $recommendationUuid, UserContext $actor, array $edits = []): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::REVIEW_RECOMMENDATIONS);
        $recommendation = $this->find($recommendationUuid, $actor);
        $accepted = $recommendation->accepted($actor->userId);
        if ($edits !== []) {
            $accepted = new \WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionRecommendation(
                $accepted->id,
                $accepted->uuid,
                $accepted->organizationId,
                $accepted->organizationUuid,
                $accepted->assessmentUuid,
                $accepted->libraryItemUuid,
                $accepted->controlCode,
                trim((string) ($edits['title'] ?? $accepted->title)) ?: $accepted->title,
                array_key_exists('description', $edits) ? (string) $edits['description'] : $accepted->description,
                array_key_exists('reason', $edits) ? $this->nullableString($edits['reason']) : $accepted->reason,
                $accepted->hierarchyLevel,
                $accepted->controlType,
                in_array((string) ($edits['priority'] ?? $accepted->priority), ['low', 'medium', 'high', 'critical'], true) ? (string) ($edits['priority'] ?? $accepted->priority) : $accepted->priority,
                $accepted->rankOrder,
                $accepted->expectedRiskReductionPct,
                isset($edits['dueDays']) || isset($edits['due_days']) ? max(1, (int) ($edits['dueDays'] ?? $edits['due_days'])) : $accepted->dueDays,
                isset($edits['followUpDays']) || isset($edits['follow_up_days']) ? max(1, (int) ($edits['followUpDays'] ?? $edits['follow_up_days'])) : $accepted->followUpDays,
                $accepted->status,
                $this->evidence($edits, $accepted->evidence),
                null,
                $accepted->reviewedAt,
                $accepted->reviewedBy,
            );
        }
        $this->repository->updateRecommendation($accepted);
        $this->audit->record('corrective_action.recommendation_accepted', 'corrective_action_recommendation', $accepted->uuid, afterState: $accepted->toView(), actorId: (string) $actor->userId, actorType: 'user');

        return $accepted->toView();
    }

    /** @return array<string, mixed> */
    public function reject(string $recommendationUuid, UserContext $actor, ?string $reason = null): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::REVIEW_RECOMMENDATIONS);
        $reason = $this->nullableString($reason);
        if ($reason === null) {
            throw new ValidationException(['reason' => 'Reject reason is required.']);
        }
        $recommendation = $this->find($recommendationUuid, $actor)->rejected($actor->userId, $reason);
        $this->repository->updateRecommendation($recommendation);
        $this->audit->record('corrective_action.recommendation_rejected', 'corrective_action_recommendation', $recommendation->uuid, afterState: $recommendation->toView(), actorId: (string) $actor->userId, actorType: 'user');

        return $recommendation->toView();
    }

    private function find(string $uuid, UserContext $actor): \WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionRecommendation
    {
        $recommendation = $this->repository->findRecommendationByUuid(UuidSupport::requireValid($uuid, 'recommendationUuid'));
        if ($recommendation === null || ($actor->organizationId !== null && $actor->organizationId !== $recommendation->organizationId)) {
            throw new NotFoundException('Recommendation not found.');
        }

        return $recommendation;
    }

    /** @param array<string, mixed> $edits @param array<string, mixed> $current */
    private function evidence(array $edits, array $current): array
    {
        $evidence = $current;
        if (array_key_exists('evidenceRequired', $edits) || array_key_exists('evidence_required', $edits)) {
            $evidence['evidence_required'] = (bool) ($edits['evidenceRequired'] ?? $edits['evidence_required']);
        }
        if (array_key_exists('evidenceTypes', $edits) || array_key_exists('evidence_types', $edits)) {
            $types = $edits['evidenceTypes'] ?? $edits['evidence_types'];
            $evidence['evidence_types'] = is_array($types) ? array_values(array_filter(array_map(static fn(mixed $value): string => strtolower(trim((string) $value)), $types), static fn(string $value): bool => $value !== '')) : [];
        }

        return $evidence;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }
}
