<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\CorrectiveAction\Domain\RecommendationRule;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Session\UserContext;

final class ListRecommendationRulesUseCase
{
    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly IPermissionService $permissions,
    ) {}

    /** @param array<string, mixed> $filters @return array{summary:array<string,int>,meta:array<string,int>,items:list<array<string,mixed>>} */
    public function execute(UserContext $actor, array $filters = []): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::MANAGE_LIBRARY);

        $allRules = $this->repository->listRecommendationRules();
        $filteredRules = $this->repository->listRecommendationRules($filters);
        $allRows = array_map(fn(RecommendationRule $rule): array => $this->normalize($rule), $allRules);

        return [
            'summary' => [
                'activeRules' => count(array_filter($allRows, static fn(array $row): bool => $row['status'] === 'active')),
                'rulesNeedingReview' => count(array_filter($allRows, static fn(array $row): bool => $row['needsReview'])),
            ],
            'meta' => [
                'total' => count($filteredRules),
            ],
            'items' => array_map(fn(RecommendationRule $rule): array => $this->normalize($rule), $filteredRules),
        ];
    }

    /** @return array<string, mixed> */
    private function normalize(RecommendationRule $rule): array
    {
        $linkedActionId = (string) ($rule->action['libraryItemUuid'] ?? '');
        $libraryItem = $linkedActionId !== '' ? $this->repository->findLibraryItemByUuid($linkedActionId) : null;
        $reviewReason = null;

        if ($linkedActionId === '' || $libraryItem === null) {
            $reviewReason = 'missing_linked_action';
        } elseif (!$libraryItem->isActive) {
            $reviewReason = 'inactive_linked_action';
        }

        return $rule->toView() + [
            'status' => $rule->isActive ? 'active' : 'inactive',
            'linkedActionId' => $linkedActionId !== '' ? $linkedActionId : null,
            'linkedActionTitle' => $libraryItem?->title,
            'assessmentType' => (string) ($rule->condition['assessmentType'] ?? 'all'),
            'triggerSummary' => $this->triggerSummary($rule),
            'priority' => $rule->weight,
            'confidenceThreshold' => $rule->condition['confidenceThreshold'] ?? null,
            'needsReview' => $reviewReason !== null,
            'reviewReason' => $reviewReason,
        ];
    }

    private function triggerSummary(RecommendationRule $rule): string
    {
        $parts = [];
        if (isset($rule->condition['riskFactor']) && $rule->condition['riskFactor'] !== '') {
            $parts[] = 'Risk ' . str_replace('_', ' ', (string) $rule->condition['riskFactor']);
        }
        if (isset($rule->condition['minScore'])) {
            $parts[] = 'Score >= ' . (string) $rule->condition['minScore'];
        }
        if (isset($rule->condition['confidenceThreshold'])) {
            $parts[] = 'Confidence >= ' . (string) $rule->condition['confidenceThreshold'];
        }
        if (isset($rule->condition['assessmentType']) && $rule->condition['assessmentType'] !== '') {
            $parts[] = strtoupper((string) $rule->condition['assessmentType']);
        }

        return $parts === [] ? 'No trigger summary' : implode(' | ', $parts);
    }
}
