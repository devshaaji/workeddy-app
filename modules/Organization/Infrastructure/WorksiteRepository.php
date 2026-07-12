<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Organization\Domain\Worksite;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;

final class WorksiteRepository implements IWorksiteRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function create(Worksite $worksite): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->connection->insert('worksites', [
            'uuid' => $worksite->getUuid() !== '' ? $worksite->getUuid() : UuidSupport::generate(),
            'organization_id' => $worksite->getOrganizationId(),
            'name' => $worksite->getName(),
            'status' => $worksite->getStatus(),
            'location' => $worksite->getLocation(),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(Worksite $worksite): void
    {
        $this->connection->update('worksites', [
            'name' => $worksite->getName(),
            'status' => $worksite->getStatus(),
            'location' => $worksite->getLocation(),
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], [
            'id' => $worksite->getId(),
        ]);
    }

    public function delete(string $uuid): void
    {
        $this->connection->update('worksites', [
            'deleted_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], [
            'uuid' => $uuid,
        ]);
    }

    public function findByUuid(string $uuid): ?Worksite
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM worksites WHERE uuid = ? AND deleted_at IS NULL',
            [$uuid],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findById(int $id): ?Worksite
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM worksites WHERE id = ? AND deleted_at IS NULL',
            [$id],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('worksites')
            ->where('organization_id = :organizationId')
            ->andWhere('deleted_at IS NULL')
            ->orderBy('name', 'ASC')
            ->setParameter('organizationId', $organizationId)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn(array $row): Worksite => $this->hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Worksite
    {
        return new Worksite(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            organizationId: (int) $row['organization_id'],
            name: (string) $row['name'],
            status: (string) ($row['status'] ?? 'active'),
            location: $row['location'] ?? null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }
}
