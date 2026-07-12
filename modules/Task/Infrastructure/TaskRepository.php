<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Task\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository;
use WorkEddy\Modules\Task\Domain\Task;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;

final class TaskRepository implements ITaskRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function create(Task $task): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->connection->insert('tasks', [
            'uuid' => $task->getUuid() !== '' ? $task->getUuid() : UuidSupport::generate(),
            'organization_id' => $task->getOrganizationId(),
            'worksite_id' => $task->getWorksiteId(),
            'department_id' => $task->getDepartmentId(),
            'job_role_id' => $task->getJobRoleId(),
            'name' => $task->getName(),
            'assessment_model' => $task->getAssessmentModel(),
            'task_code' => $task->getTaskCode(),
            'status' => $task->getStatus(),
            'description' => $task->getDescription(),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(Task $task): void
    {
        $this->connection->update(
            'tasks',
            [
                'worksite_id' => $task->getWorksiteId(),
                'department_id' => $task->getDepartmentId(),
                'job_role_id' => $task->getJobRoleId(),
                'name' => $task->getName(),
                'assessment_model' => $task->getAssessmentModel(),
                'task_code' => $task->getTaskCode(),
                'status' => $task->getStatus(),
                'description' => $task->getDescription(),
                'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            ],
            ['uuid' => $task->getUuid()],
        );
    }

    public function delete(string $uuid): void
    {
        $this->connection->update(
            'tasks',
            [
                'deleted_at' => $this->clock->now()->format('Y-m-d H:i:s'),
                'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            ],
            ['uuid' => $uuid],
        );
    }

    public function findByUuid(string $uuid): ?Task
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM tasks WHERE uuid = ? AND deleted_at IS NULL',
            [$uuid],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('tasks')
            ->where('organization_id = :organizationId')
            ->andWhere('deleted_at IS NULL')
            ->setParameter('organizationId', $organizationId)
            ->orderBy('name', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn(array $row): Task => $this->hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Task
    {
        return new Task(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            organizationId: (int) $row['organization_id'],
            worksiteId: isset($row['worksite_id']) ? (int) $row['worksite_id'] : null,
            departmentId: isset($row['department_id']) ? (int) $row['department_id'] : null,
            jobRoleId: isset($row['job_role_id']) ? (int) $row['job_role_id'] : null,
            name: (string) $row['name'],
            assessmentModel: (string) ($row['assessment_model'] ?? 'reba'),
            taskCode: $row['task_code'] ?? null,
            status: (string) ($row['status'] ?? 'active'),
            description: $row['description'] ?? null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }
}
