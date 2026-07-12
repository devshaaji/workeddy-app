<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveAction;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionLibraryItem;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionRecommendation;
use WorkEddy\Modules\CorrectiveAction\Domain\RecommendationRule;

final class CorrectiveActionRepository implements ICorrectiveActionRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function replaceRecommendationsForAssessment(string $assessmentUuid, array $recommendations): void
    {
        $this->connection->delete('corrective_action_recommendations', ['assessment_uuid' => $assessmentUuid, 'status' => 'generated']);
        foreach ($recommendations as $recommendation) {
            $this->insertRecommendation($recommendation);
        }
    }

    public function listRecommendationsByAssessment(string $assessmentUuid): array
    {
        return array_map(fn(array $row): CorrectiveActionRecommendation => $this->hydrateRecommendation($row), $this->connection->fetchAllAssociative('SELECT * FROM corrective_action_recommendations WHERE assessment_uuid = ? ORDER BY rank_order ASC, id ASC', [$assessmentUuid]));
    }

    public function findRecommendationByUuid(string $uuid): ?CorrectiveActionRecommendation
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM corrective_action_recommendations WHERE uuid = ?', [$uuid]);
        return $row === false ? null : $this->hydrateRecommendation($row);
    }

    public function updateRecommendation(CorrectiveActionRecommendation $recommendation): void
    {
        $this->connection->update('corrective_action_recommendations', [
            'title' => $recommendation->title,
            'description' => $recommendation->description,
            'reject_reason' => $recommendation->rejectReason,
            'priority' => $recommendation->priority,
            'due_days' => $recommendation->dueDays,
            'follow_up_days' => $recommendation->followUpDays,
            'evidence_json' => json_encode($recommendation->evidence, JSON_THROW_ON_ERROR),
            'status' => $recommendation->status,
            'reviewed_at' => $recommendation->reviewedAt,
            'reviewed_by' => $recommendation->reviewedBy,
            'updated_at' => $this->now(),
        ], ['uuid' => $recommendation->uuid]);
    }

    public function createAction(CorrectiveAction $action): int
    {
        $this->connection->insert('corrective_actions', [
            'uuid' => $action->uuid,
            'organization_id' => $action->organizationId,
            'organization_uuid' => $action->organizationUuid,
            'assessment_uuid' => $action->assessmentUuid,
            'recommendation_uuid' => $action->recommendationUuid,
            'library_item_uuid' => $action->libraryItemUuid,
            'title' => $action->title,
            'description' => $action->description,
            'reason' => $action->reason,
            'control_type' => $action->controlType,
            'hierarchy_level' => $action->hierarchyLevel,
            'priority' => $action->priority,
            'status' => $action->status,
            'assigned_to_user_id' => $action->assignedToUserId,
            'assigned_by_user_id' => $action->assignedByUserId,
            'due_date' => $action->dueDate,
            'follow_up_assessment_due_date' => $action->followUpAssessmentDueDate,
            'evidence_requirements_json' => json_encode($action->evidenceRequirements, JSON_THROW_ON_ERROR),
            'reject_reason' => $action->rejectReason,
            'completed_at' => $action->completedAt,
            'verified_at' => $action->verifiedAt,
            'rejected_at' => $action->rejectedAt,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function updateAction(CorrectiveAction $action): void
    {
        $this->connection->update('corrective_actions', [
            'status' => $action->status,
            'assigned_to_user_id' => $action->assignedToUserId,
            'assigned_by_user_id' => $action->assignedByUserId,
            'due_date' => $action->dueDate,
            'follow_up_assessment_due_date' => $action->followUpAssessmentDueDate,
            'evidence_requirements_json' => json_encode($action->evidenceRequirements, JSON_THROW_ON_ERROR),
            'reject_reason' => $action->rejectReason,
            'completed_at' => $action->completedAt,
            'verified_at' => $action->verifiedAt,
            'rejected_at' => $action->rejectedAt,
            'updated_at' => $this->now(),
        ], ['uuid' => $action->uuid]);
    }

    public function findActionByUuid(string $uuid): ?CorrectiveAction
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM corrective_actions WHERE uuid = ?', [$uuid]);
        return $row === false ? null : $this->hydrateAction($row);
    }

    public function listActionsByOrganizationId(int $organizationId, array $filters = []): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('corrective_actions')
            ->where('organization_id = :organizationId')
            ->setParameter('organizationId', $organizationId)
            ->orderBy('created_at', 'DESC')
            ->setMaxResults((int) ($filters['limit'] ?? 100));
        if (isset($filters['status'])) {
            $qb->andWhere('status = :status')->setParameter('status', (string) $filters['status']);
        }
        return array_map(fn(array $row): CorrectiveAction => $this->hydrateAction($row), $qb->executeQuery()->fetchAllAssociative());
    }

    public function addEvidence(array $data): array
    {
        $this->connection->insert('corrective_action_evidence', [
            'uuid' => $data['uuid'],
            'action_uuid' => $data['actionUuid'],
            'storage_file_uuid' => $data['storageFileUuid'],
            'evidence_type' => $data['evidenceType'],
            'notes' => $data['notes'] ?? null,
            'uploaded_by' => $data['uploadedBy'],
            'created_at' => $this->now(),
        ]);

        return $data;
    }

    public function listEvidenceByActionUuid(string $actionUuid): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM corrective_action_evidence WHERE action_uuid = ? ORDER BY created_at DESC, id DESC', [$actionUuid]);
    }

    public function addStatusHistory(array $data): void
    {
        $this->connection->insert('corrective_action_status_history', [
            'action_uuid' => $data['actionUuid'],
            'status' => $data['status'],
            'actor_id' => $data['actorId'],
            'notes' => $data['notes'] ?? null,
            'created_at' => $this->now(),
        ]);
    }

    public function listStatusHistoryByActionUuid(string $actionUuid): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM corrective_action_status_history WHERE action_uuid = ? ORDER BY created_at DESC, id DESC', [$actionUuid]);
    }

    public function upsertLibraryItem(CorrectiveActionLibraryItem $item): CorrectiveActionLibraryItem
    {
        $data = [
            'uuid' => $item->uuid,
            'title' => $item->title,
            'description' => $item->description,
            'reason' => $item->reason,
            'control_type' => $item->controlType,
            'hierarchy_level' => $item->hierarchyLevel,
            'risk_factor' => $item->riskFactor,
            'task_type' => $item->taskType,
            'industry' => $item->industry,
            'priority' => $item->priority,
            'due_days' => $item->dueDays,
            'evidence_required' => $item->evidenceRequired ? 1 : 0,
            'evidence_types_json' => json_encode($item->evidenceTypes, JSON_THROW_ON_ERROR),
            'follow_up_days' => $item->followUpDays,
            'is_active' => $item->isActive ? 1 : 0,
            'updated_at' => $this->now(),
        ];
        $existing = $this->connection->fetchAssociative('SELECT id FROM corrective_action_library WHERE uuid = ?', [$item->uuid]);
        if ($existing === false) {
            $this->connection->insert('corrective_action_library', $data + ['created_at' => $this->now()]);
        } else {
            $this->connection->update('corrective_action_library', $data, ['uuid' => $item->uuid]);
        }

        $row = $this->connection->fetchAssociative('SELECT * FROM corrective_action_library WHERE uuid = ?', [$item->uuid]);
        return $this->hydrateLibraryItem($row ?: $data);
    }

    public function findLibraryItemByUuid(string $uuid): ?CorrectiveActionLibraryItem
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM corrective_action_library WHERE uuid = ?', [$uuid]);
        return $row === false ? null : $this->hydrateLibraryItem($row);
    }

    public function countRulesForLibraryItem(string $libraryItemUuid): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM recommendation_rules WHERE action_json LIKE ?', ['%"libraryItemUuid":"' . $libraryItemUuid . '"%']);
    }

    public function listLibraryItems(array $filters = []): array
    {
        $qb = $this->connection->createQueryBuilder()->select('*')->from('corrective_action_library')->orderBy('hierarchy_level', 'ASC')->addOrderBy('title', 'ASC')->setMaxResults((int) ($filters['limit'] ?? 100));
        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $qb->andWhere('(title LIKE :search OR description LIKE :search)')
                ->setParameter('search', '%' . trim((string) $filters['search']) . '%');
        }
        foreach (['risk_factor', 'task_type', 'industry'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $qb->andWhere($field . ' = :' . $field)->setParameter($field, (string) $filters[$field]);
            }
        }
        if (isset($filters['category']) && $filters['category'] !== '') {
            $qb->andWhere('hierarchy_level = :category')->setParameter('category', (string) $filters['category']);
        }
        if (isset($filters['risk_level']) && $filters['risk_level'] !== '') {
            $qb->andWhere('priority = :riskLevel')->setParameter('riskLevel', (string) $filters['risk_level']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $qb->andWhere('is_active = :isActive')->setParameter('isActive', $filters['status'] === 'active' ? 1 : 0);
        }
        return array_map(fn(array $row): CorrectiveActionLibraryItem => $this->hydrateLibraryItem($row), $qb->executeQuery()->fetchAllAssociative());
    }

    public function upsertRecommendationRule(RecommendationRule $rule): RecommendationRule
    {
        $data = [
            'uuid' => $rule->uuid,
            'condition_json' => json_encode($rule->condition, JSON_THROW_ON_ERROR),
            'action_json' => json_encode($rule->action, JSON_THROW_ON_ERROR),
            'weight' => $rule->weight,
            'is_active' => $rule->isActive ? 1 : 0,
            'updated_at' => $this->now(),
        ];
        $existing = $this->connection->fetchAssociative('SELECT id FROM recommendation_rules WHERE uuid = ?', [$rule->uuid]);
        if ($existing === false) {
            $this->connection->insert('recommendation_rules', $data + ['created_at' => $this->now()]);
        } else {
            $this->connection->update('recommendation_rules', $data, ['uuid' => $rule->uuid]);
        }

        $row = $this->connection->fetchAssociative('SELECT * FROM recommendation_rules WHERE uuid = ?', [$rule->uuid]);
        return $this->hydrateRule($row ?: $data);
    }

    public function listRecommendationRules(array $filters = []): array
    {
        $rules = array_map(
            fn(array $row): RecommendationRule => $this->hydrateRule($row),
            $this->connection->fetchAllAssociative('SELECT * FROM recommendation_rules ORDER BY weight DESC, id ASC'),
        );

        return array_values(array_filter($rules, function (RecommendationRule $rule) use ($filters): bool {
            $search = trim((string) ($filters['search'] ?? ''));
            $status = (string) ($filters['status'] ?? '');
            $assessmentType = (string) ($filters['assessment_type'] ?? '');
            $linkedAction = (string) ($filters['linked_action'] ?? '');
            $reviewNeeded = (string) ($filters['review_needed'] ?? '');

            if ($status !== '') {
                $expectedActive = $status === 'active';
                if ($rule->isActive !== $expectedActive) {
                    return false;
                }
            }

            if ($assessmentType !== '' && (string) ($rule->condition['assessmentType'] ?? '') !== $assessmentType) {
                return false;
            }

            if ($linkedAction !== '' && (string) ($rule->action['libraryItemUuid'] ?? '') !== $linkedAction) {
                return false;
            }

            $linkedItem = null;
            $linkedActionId = (string) ($rule->action['libraryItemUuid'] ?? '');
            if ($linkedActionId !== '') {
                $linkedItem = $this->findLibraryItemByUuid($linkedActionId);
            }
            $needsReview = $linkedActionId === '' || $linkedItem === null || !$linkedItem->isActive;

            if ($reviewNeeded !== '' && $needsReview !== ($reviewNeeded === '1')) {
                return false;
            }

            if ($search !== '') {
                $haystack = strtolower(implode(' ', [
                    $linkedItem?->title ?? '',
                    $rule->uuid,
                    (string) ($rule->condition['riskFactor'] ?? ''),
                    (string) ($rule->condition['assessmentType'] ?? ''),
                ]));
                if (!str_contains($haystack, strtolower($search))) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function listDueActions(string $beforeDate, int $limit = 100): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('corrective_actions')
            ->where('due_date IS NOT NULL')
            ->andWhere('due_date < :beforeDate')
            ->andWhere('status IN (:statuses)')
            ->setParameter('beforeDate', $beforeDate)
            ->setParameter('statuses', ['assigned', 'in_progress'], \Doctrine\DBAL\ArrayParameterType::STRING)
            ->orderBy('due_date', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn(array $row): CorrectiveAction => $this->hydrateAction($row), $rows);
    }

    public function listDueFollowUps(string $beforeDate, int $limit = 100): array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('corrective_action_follow_ups')
            ->where('due_date <= :beforeDate')
            ->andWhere('status = :status')
            ->setParameter('beforeDate', $beforeDate)
            ->setParameter('status', 'scheduled')
            ->orderBy('due_date', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function createOrUpdateFollowUp(string $actionUuid, string $dueDate, ?string $followUpAssessmentUuid = null, string $status = 'scheduled'): void
    {
        $existing = $this->connection->fetchAssociative('SELECT id FROM corrective_action_follow_ups WHERE action_uuid = ?', [$actionUuid]);
        $data = [
            'action_uuid' => $actionUuid,
            'due_date' => $dueDate,
            'follow_up_assessment_uuid' => $followUpAssessmentUuid,
            'status' => $status,
            'updated_at' => $this->now(),
        ];

        if ($existing === false) {
            $this->connection->insert('corrective_action_follow_ups', $data + ['created_at' => $this->now()]);
            return;
        }

        $this->connection->update('corrective_action_follow_ups', $data, ['action_uuid' => $actionUuid]);
    }

    public function updateFollowUpStatus(string $actionUuid, string $status): void
    {
        $this->connection->update('corrective_action_follow_ups', [
            'status' => $status,
            'updated_at' => $this->now(),
        ], ['action_uuid' => $actionUuid]);
    }

    private function insertRecommendation(CorrectiveActionRecommendation $recommendation): void
    {
        $this->connection->insert('corrective_action_recommendations', [
            'uuid' => $recommendation->uuid,
            'organization_id' => $recommendation->organizationId,
            'organization_uuid' => $recommendation->organizationUuid,
            'assessment_uuid' => $recommendation->assessmentUuid,
            'library_item_uuid' => $recommendation->libraryItemUuid,
            'control_code' => $recommendation->controlCode,
            'title' => $recommendation->title,
            'description' => $recommendation->description,
            'reason' => $recommendation->reason,
            'hierarchy_level' => $recommendation->hierarchyLevel,
            'control_type' => $recommendation->controlType,
            'priority' => $recommendation->priority,
            'rank_order' => $recommendation->rankOrder,
            'expected_risk_reduction_pct' => $recommendation->expectedRiskReductionPct,
            'due_days' => $recommendation->dueDays,
            'follow_up_days' => $recommendation->followUpDays,
            'status' => $recommendation->status,
            'evidence_json' => json_encode($recommendation->evidence, JSON_THROW_ON_ERROR),
            'reject_reason' => $recommendation->rejectReason,
            'reviewed_at' => $recommendation->reviewedAt,
            'reviewed_by' => $recommendation->reviewedBy,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);
    }

    private function hydrateRecommendation(array $row): CorrectiveActionRecommendation
    {
        return new CorrectiveActionRecommendation((int) $row['id'], (string) $row['uuid'], (int) $row['organization_id'], (string) $row['organization_uuid'], (string) $row['assessment_uuid'], $row['library_item_uuid'] ?? null, (string) $row['control_code'], (string) $row['title'], $row['description'] ?? null, $row['reason'] ?? null, (string) $row['hierarchy_level'], (string) $row['control_type'], (string) $row['priority'], (int) $row['rank_order'], (float) $row['expected_risk_reduction_pct'], isset($row['due_days']) ? (int) $row['due_days'] : null, isset($row['follow_up_days']) ? (int) $row['follow_up_days'] : null, (string) $row['status'], $this->decode($row['evidence_json'] ?? null), $row['reject_reason'] ?? null, $row['reviewed_at'] ?? null, isset($row['reviewed_by']) ? (int) $row['reviewed_by'] : null);
    }

    private function hydrateAction(array $row): CorrectiveAction
    {
        return new CorrectiveAction((int) $row['id'], (string) $row['uuid'], (int) $row['organization_id'], (string) $row['organization_uuid'], (string) $row['assessment_uuid'], $row['recommendation_uuid'] ?? null, $row['library_item_uuid'] ?? null, (string) $row['title'], $row['description'] ?? null, $row['reason'] ?? null, (string) $row['control_type'], (string) $row['hierarchy_level'], (string) $row['priority'], (string) $row['status'], isset($row['assigned_to_user_id']) ? (int) $row['assigned_to_user_id'] : null, isset($row['assigned_by_user_id']) ? (int) $row['assigned_by_user_id'] : null, $row['due_date'] ?? null, $row['follow_up_assessment_due_date'] ?? null, $this->decode($row['evidence_requirements_json'] ?? null), $row['reject_reason'] ?? null, $row['completed_at'] ?? null, $row['verified_at'] ?? null, $row['rejected_at'] ?? null);
    }

    private function hydrateLibraryItem(array $row): CorrectiveActionLibraryItem
    {
        return new CorrectiveActionLibraryItem(isset($row['id']) ? (int) $row['id'] : null, (string) $row['uuid'], (string) $row['title'], $row['description'] ?? null, $row['reason'] ?? null, (string) $row['control_type'], (string) $row['hierarchy_level'], $row['risk_factor'] ?? null, $row['task_type'] ?? null, $row['industry'] ?? null, (string) $row['priority'], (int) $row['due_days'], (bool) $row['evidence_required'], $this->decode($row['evidence_types_json'] ?? null), isset($row['follow_up_days']) ? (int) $row['follow_up_days'] : null, isset($row['is_active']) ? (bool) $row['is_active'] : true, $row['updated_at'] ?? null);
    }

    private function hydrateRule(array $row): RecommendationRule
    {
        return new RecommendationRule(isset($row['id']) ? (int) $row['id'] : null, (string) $row['uuid'], $this->decode($row['condition_json'] ?? null), $this->decode($row['action_json'] ?? null), (int) $row['weight'], (bool) $row['is_active'], $row['updated_at'] ?? null);
    }

    private function decode(mixed $value): array
    {
        return $value === null || $value === '' ? [] : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
