<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Organization;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;

final class OrganizationRepository implements IOrganizationRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function create(Organization $organization): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->connection->insert('organizations', [
            'uuid' => $organization->getUuid() !== '' ? $organization->getUuid() : UuidSupport::generate(),
            'name' => $organization->getName(),
            'slug' => $organization->getSlug(),
            'status' => $organization->getStatus(),
            'contact_email' => $organization->getContactEmail(),
            'phone' => $organization->getPhone(),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(Organization $organization): void
    {
        $this->connection->update('organizations', [
            'name' => $organization->getName(),
            'slug' => $organization->getSlug(),
            'status' => $organization->getStatus(),
            'contact_email' => $organization->getContactEmail(),
            'phone' => $organization->getPhone(),
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], [
            'uuid' => $organization->getUuid(),
        ]);
    }

    public function softDelete(string $uuid): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->connection->update('organizations', [
            'status' => 'deleted',
            'deleted_at' => $now,
            'updated_at' => $now,
        ], [
            'uuid' => $uuid,
        ]);
    }

    public function findById(int $id): ?Organization
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM organizations WHERE id = ? AND deleted_at IS NULL',
            [$id],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByUuid(string $uuid): ?Organization
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM organizations WHERE uuid = ? AND deleted_at IS NULL',
            [$uuid],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findBySlug(string $slug): ?Organization
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM organizations WHERE slug = ? AND deleted_at IS NULL',
            [$slug],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAll(int $limit = 50, int $offset = 0): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('organizations')
            ->where('deleted_at IS NULL')
            ->orderBy('name', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn(array $row): Organization => $this->hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Organization
    {
        return new Organization(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            name: (string) $row['name'],
            slug: (string) $row['slug'],
            status: (string) ($row['status'] ?? 'active'),
            contactEmail: $row['contact_email'] ?? null,
            phone: $row['phone'] ?? null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }
}
