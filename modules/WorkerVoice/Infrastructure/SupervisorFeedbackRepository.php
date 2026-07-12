<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\WorkerVoice\Domain\Contracts\ISupervisorFeedbackRepository;
use WorkEddy\Modules\WorkerVoice\Domain\SupervisorFeedback;

final class SupervisorFeedbackRepository implements ISupervisorFeedbackRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function create(SupervisorFeedback $feedback): int
    {
        $now = date('Y-m-d H:i:s');
        $this->connection->insert('supervisor_feedback', [
            'uuid' => $feedback->uuid,
            'organization_id' => $feedback->organizationId,
            'organization_uuid' => $feedback->organizationUuid,
            'task_id' => $feedback->taskId,
            'task_uuid' => $feedback->taskUuid,
            'assessment_uuid' => $feedback->assessmentUuid,
            'worksite_id' => $feedback->worksiteId,
            'worksite_uuid' => $feedback->worksiteUuid,
            'department_id' => $feedback->departmentId,
            'department_uuid' => $feedback->departmentUuid,
            'job_role_id' => $feedback->jobRoleId,
            'job_role_uuid' => $feedback->jobRoleUuid,
            'submitted_by_user_id' => $feedback->submittedByUserId,
            'body_region' => $feedback->bodyRegion,
            'observed_risk_level' => $feedback->observedRiskLevel,
            'observed_issue_type' => $feedback->observedIssueType,
            'frequency_level' => $feedback->frequencyLevel,
            'severity_level' => $feedback->severityLevel,
            'suggested_change' => $feedback->suggestedChange,
            'notes' => $feedback->notes,
            'created_at' => $feedback->createdAt ?? $now,
            'updated_at' => $feedback->updatedAt ?? $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function findByUuid(string $uuid): ?SupervisorFeedback
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM supervisor_feedback WHERE uuid = ?', [$uuid]);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAllByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('supervisor_feedback')
            ->where('organization_id = :organizationId')
            ->setParameter('organizationId', $organizationId)
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $map = [
            'taskUuid' => 'task_uuid',
            'assessmentUuid' => 'assessment_uuid',
            'bodyRegion' => 'body_region',
            'worksiteUuid' => 'worksite_uuid',
            'departmentUuid' => 'department_uuid',
            'jobRoleUuid' => 'job_role_uuid',
            'observedRiskLevel' => 'observed_risk_level',
        ];
        foreach ($map as $filterKey => $column) {
            $value = $filters[$filterKey] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $qb->andWhere($column . ' = :' . $filterKey)->setParameter($filterKey, $value);
            }
        }
        if (is_string($filters['dateFrom'] ?? null) && $filters['dateFrom'] !== '') {
            $qb->andWhere('created_at >= :dateFrom')->setParameter('dateFrom', $filters['dateFrom'] . ' 00:00:00');
        }
        if (is_string($filters['dateTo'] ?? null) && $filters['dateTo'] !== '') {
            $qb->andWhere('created_at <= :dateTo')->setParameter('dateTo', $filters['dateTo'] . ' 23:59:59');
        }

        return array_map(fn(array $row): SupervisorFeedback => $this->hydrate($row), $qb->executeQuery()->fetchAllAssociative());
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): SupervisorFeedback
    {
        return new SupervisorFeedback(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            organizationId: (int) $row['organization_id'],
            organizationUuid: (string) $row['organization_uuid'],
            taskId: isset($row['task_id']) ? (int) $row['task_id'] : null,
            taskUuid: isset($row['task_uuid']) ? (string) $row['task_uuid'] : null,
            assessmentUuid: isset($row['assessment_uuid']) ? (string) $row['assessment_uuid'] : null,
            worksiteId: isset($row['worksite_id']) ? (int) $row['worksite_id'] : null,
            worksiteUuid: isset($row['worksite_uuid']) ? (string) $row['worksite_uuid'] : null,
            departmentId: isset($row['department_id']) ? (int) $row['department_id'] : null,
            departmentUuid: isset($row['department_uuid']) ? (string) $row['department_uuid'] : null,
            jobRoleId: isset($row['job_role_id']) ? (int) $row['job_role_id'] : null,
            jobRoleUuid: isset($row['job_role_uuid']) ? (string) $row['job_role_uuid'] : null,
            submittedByUserId: (int) $row['submitted_by_user_id'],
            bodyRegion: $row['body_region'] ?? null,
            observedRiskLevel: (string) $row['observed_risk_level'],
            observedIssueType: (string) $row['observed_issue_type'],
            frequencyLevel: (int) $row['frequency_level'],
            severityLevel: (int) $row['severity_level'],
            suggestedChange: $row['suggested_change'] ?? null,
            notes: $row['notes'] ?? null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}
