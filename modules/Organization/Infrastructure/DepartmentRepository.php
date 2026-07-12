<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Department;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;

final class DepartmentRepository implements IDepartmentRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function create(Department $department): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->connection->insert('departments', [
            'uuid' => $department->getUuid() !== '' ? $department->getUuid() : UuidSupport::generate(),
            'organization_id' => $department->getOrganizationId(),
            'worksite_id' => $department->getWorksiteId(),
            'parent_department_id' => $department->getParentDepartmentId(),
            'name' => $department->getName(),
            'status' => $department->getStatus(),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(Department $department): void
    {
        $this->connection->update('departments', [
            'worksite_id' => $department->getWorksiteId(),
            'parent_department_id' => $department->getParentDepartmentId(),
            'name' => $department->getName(),
            'status' => $department->getStatus(),
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], [
            'id' => $department->getId(),
        ]);
    }

    public function delete(string $uuid): void
    {
        $this->connection->update('departments', [
            'deleted_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], [
            'uuid' => $uuid,
        ]);
    }

    public function findByUuid(string $uuid): ?Department
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM departments WHERE uuid = ? AND deleted_at IS NULL',
            [$uuid],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findById(int $id): ?Department
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM departments WHERE id = ? AND deleted_at IS NULL',
            [$id],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('departments')
            ->where('organization_id = :organizationId')
            ->andWhere('deleted_at IS NULL')
            ->orderBy('name', 'ASC')
            ->setParameter('organizationId', $organizationId)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn(array $row): Department => $this->hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Department
    {
        return new Department(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            organizationId: (int) $row['organization_id'],
            worksiteId: isset($row['worksite_id']) ? (int) $row['worksite_id'] : null,
            parentDepartmentId: isset($row['parent_department_id']) ? (int) $row['parent_department_id'] : null,
            name: (string) $row['name'],
            status: (string) ($row['status'] ?? 'active'),
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }
}
