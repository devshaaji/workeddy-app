<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\JobRole;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;

final class JobRoleRepository implements IJobRoleRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function create(JobRole $jobRole): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->connection->insert('job_roles', [
            'uuid' => $jobRole->getUuid() !== '' ? $jobRole->getUuid() : UuidSupport::generate(),
            'organization_id' => $jobRole->getOrganizationId(),
            'department_id' => $jobRole->getDepartmentId(),
            'name' => $jobRole->getName(),
            'status' => $jobRole->getStatus(),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(JobRole $jobRole): void
    {
        $this->connection->update('job_roles', [
            'department_id' => $jobRole->getDepartmentId(),
            'name' => $jobRole->getName(),
            'status' => $jobRole->getStatus(),
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], [
            'id' => $jobRole->getId(),
        ]);
    }

    public function delete(string $uuid): void
    {
        $this->connection->update('job_roles', [
            'deleted_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], [
            'uuid' => $uuid,
        ]);
    }

    public function findByUuid(string $uuid): ?JobRole
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM job_roles WHERE uuid = ? AND deleted_at IS NULL',
            [$uuid],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findById(int $id): ?JobRole
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM job_roles WHERE id = ? AND deleted_at IS NULL',
            [$id],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('job_roles')
            ->where('organization_id = :organizationId')
            ->andWhere('deleted_at IS NULL')
            ->orderBy('name', 'ASC')
            ->setParameter('organizationId', $organizationId)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn(array $row): JobRole => $this->hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): JobRole
    {
        return new JobRole(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            organizationId: (int) $row['organization_id'],
            departmentId: isset($row['department_id']) ? (int) $row['department_id'] : null,
            name: (string) $row['name'],
            status: (string) ($row['status'] ?? 'active'),
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }
}
