<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Domain\Contracts;

use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveAction;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionLibraryItem;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionRecommendation;
use WorkEddy\Modules\CorrectiveAction\Domain\RecommendationRule;

interface ICorrectiveActionRepository
{
    /** @param list<CorrectiveActionRecommendation> $recommendations */
    public function replaceRecommendationsForAssessment(string $assessmentUuid, array $recommendations): void;
    /** @return list<CorrectiveActionRecommendation> */
    public function listRecommendationsByAssessment(string $assessmentUuid): array;
    public function findRecommendationByUuid(string $uuid): ?CorrectiveActionRecommendation;
    public function updateRecommendation(CorrectiveActionRecommendation $recommendation): void;
    public function createAction(CorrectiveAction $action): int;
    public function updateAction(CorrectiveAction $action): void;
    public function findActionByUuid(string $uuid): ?CorrectiveAction;
    /** @return list<CorrectiveAction> */
    public function listActionsByOrganizationId(int $organizationId, array $filters = []): array;
    /** @param array<string, mixed> $data */
    public function addEvidence(array $data): array;
    /** @return list<array<string, mixed>> */
    public function listEvidenceByActionUuid(string $actionUuid): array;
    /** @param array<string, mixed> $data */
    public function addStatusHistory(array $data): void;
    /** @return list<array<string, mixed>> */
    public function listStatusHistoryByActionUuid(string $actionUuid): array;
    public function upsertLibraryItem(CorrectiveActionLibraryItem $item): CorrectiveActionLibraryItem;
    public function findLibraryItemByUuid(string $uuid): ?CorrectiveActionLibraryItem;
    public function countRulesForLibraryItem(string $libraryItemUuid): int;
    /** @return list<CorrectiveActionLibraryItem> */
    public function listLibraryItems(array $filters = []): array;
    public function upsertRecommendationRule(RecommendationRule $rule): RecommendationRule;
    /** @return list<RecommendationRule> */
    public function listRecommendationRules(array $filters = []): array;
    /** @return list<CorrectiveAction> */
    public function listDueActions(string $beforeDate, int $limit = 100): array;
    /** @return list<array<string, mixed>> */
    public function listDueFollowUps(string $beforeDate, int $limit = 100): array;
    public function createOrUpdateFollowUp(string $actionUuid, string $dueDate, ?string $followUpAssessmentUuid = null, string $status = 'scheduled'): void;
    public function updateFollowUpStatus(string $actionUuid, string $status): void;
}
