<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\WorkerVoice\Domain\Contracts\IWorkerVoiceRepository;
use WorkEddy\Modules\WorkerVoice\Domain\WorkerFeedback;

final class WorkerVoiceRepository implements IWorkerVoiceRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function create(WorkerFeedback $feedback): int
    {
        $now = date('Y-m-d H:i:s');
        $this->connection->insert('worker_feedback', [
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
            'anonymous_status' => $feedback->anonymousStatus ? 1 : 0,
            'body_region' => $feedback->bodyRegion,
            'has_discomfort' => $feedback->hasDiscomfort ? 1 : 0,
            'discomfort_level' => $feedback->discomfortLevel,
            'frequency_level' => $feedback->frequencyLevel,
            'difficulty_level' => $feedback->difficultyLevel,
            'reporting_comfort_level' => $feedback->reportingComfortLevel,
            'pain_7_day_level' => $feedback->pain7DayLevel,
            'pain_30_day_level' => $feedback->pain30DayLevel,
            'suggested_change' => $feedback->suggestedChange,
            'metadata_json' => $this->encode($feedback->metadata),
            'created_at' => $feedback->createdAt ?? $now,
            'updated_at' => $feedback->updatedAt ?? $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function findByUuid(string $uuid): ?WorkerFeedback
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM worker_feedback WHERE uuid = ?', [$uuid]);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAllByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('worker_feedback')
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
        ];
        foreach ($map as $filterKey => $column) {
            $value = $filters[$filterKey] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $qb->andWhere($column . ' = :' . $filterKey)->setParameter($filterKey, $value);
            }
        }

        if (array_key_exists('anonymousStatus', $filters) && $filters['anonymousStatus'] !== null && $filters['anonymousStatus'] !== '') {
            $value = $filters['anonymousStatus'];
            $bool = is_bool($value) ? $value : in_array($value, [1, '1', 'true'], true);
            $qb->andWhere('anonymous_status = :anonymousStatus')->setParameter('anonymousStatus', $bool ? 1 : 0);
        }
        if (is_string($filters['dateFrom'] ?? null) && $filters['dateFrom'] !== '') {
            $qb->andWhere('created_at >= :dateFrom')->setParameter('dateFrom', $filters['dateFrom'] . ' 00:00:00');
        }
        if (is_string($filters['dateTo'] ?? null) && $filters['dateTo'] !== '') {
            $qb->andWhere('created_at <= :dateTo')->setParameter('dateTo', $filters['dateTo'] . ' 23:59:59');
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(fn(array $row): WorkerFeedback => $this->hydrate($row), $rows);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): WorkerFeedback
    {
        return new WorkerFeedback(
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
            submittedByUserId: isset($row['submitted_by_user_id']) ? (int) $row['submitted_by_user_id'] : null,
            anonymousStatus: (bool) ($row['anonymous_status'] ?? false),
            bodyRegion: (string) $row['body_region'],
            hasDiscomfort: (bool) ($row['has_discomfort'] ?? true),
            discomfortLevel: (int) $row['discomfort_level'],
            frequencyLevel: (int) $row['frequency_level'],
            difficultyLevel: (int) $row['difficulty_level'],
            reportingComfortLevel: (int) $row['reporting_comfort_level'],
            pain7DayLevel: (int) $row['pain_7_day_level'],
            pain30DayLevel: (int) $row['pain_30_day_level'],
            suggestedChange: $row['suggested_change'] ?? null,
            metadata: $this->decode($row['metadata_json'] ?? null),
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
