<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Organization\Domain\Contracts\IPilotSiteRepository;
use WorkEddy\Modules\Organization\Domain\PilotSite;
use WorkEddy\Platform\Clock\IClock;

final class PilotSiteRepository implements IPilotSiteRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function create(PilotSite $pilotSite): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->connection->insert('pilot_sites', [
            'uuid' => $pilotSite->getUuid(),
            'organization_id' => $pilotSite->getOrganizationId(),
            'organization_uuid' => $pilotSite->getOrganizationUuid(),
            'worksite_id' => $pilotSite->getWorksiteId(),
            'worksite_uuid' => $pilotSite->getWorksiteUuid(),
            'enrollment_date' => $pilotSite->getEnrollmentDate(),
            'pilot_status' => $pilotSite->getPilotStatus(),
            'target_worker_count' => $pilotSite->getTargetWorkerCount(),
            'actual_worker_count' => $pilotSite->getActualWorkerCount(),
            'industry' => $pilotSite->getIndustry(),
            'notes' => $pilotSite->getNotes(),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(PilotSite $pilotSite): void
    {
        $this->connection->update('pilot_sites', [
            'enrollment_date' => $pilotSite->getEnrollmentDate(),
            'pilot_status' => $pilotSite->getPilotStatus(),
            'target_worker_count' => $pilotSite->getTargetWorkerCount(),
            'actual_worker_count' => $pilotSite->getActualWorkerCount(),
            'industry' => $pilotSite->getIndustry(),
            'notes' => $pilotSite->getNotes(),
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], [
            'uuid' => $pilotSite->getUuid(),
        ]);
    }

    public function findByUuid(string $uuid): ?PilotSite
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM pilot_sites WHERE uuid = ? AND deleted_at IS NULL',
            [$uuid],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAllByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('pilot_sites')
            ->where('organization_id = :organizationId')
            ->andWhere('deleted_at IS NULL')
            ->setParameter('organizationId', $organizationId)
            ->orderBy('enrollment_date', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if (is_string($filters['worksiteUuid'] ?? null) && trim($filters['worksiteUuid']) !== '') {
            $qb->andWhere('worksite_uuid = :worksiteUuid')->setParameter('worksiteUuid', trim((string) $filters['worksiteUuid']));
        }
        if (is_string($filters['pilotStatus'] ?? null) && trim($filters['pilotStatus']) !== '') {
            $qb->andWhere('pilot_status = :pilotStatus')->setParameter('pilotStatus', trim((string) $filters['pilotStatus']));
        }
        if (is_string($filters['industry'] ?? null) && trim($filters['industry']) !== '') {
            $qb->andWhere('industry = :industry')->setParameter('industry', trim((string) $filters['industry']));
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(fn(array $row): PilotSite => $this->hydrate($row), $rows);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): PilotSite
    {
        return new PilotSite(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            organizationId: (int) $row['organization_id'],
            organizationUuid: (string) $row['organization_uuid'],
            worksiteId: (int) $row['worksite_id'],
            worksiteUuid: (string) $row['worksite_uuid'],
            enrollmentDate: (string) $row['enrollment_date'],
            pilotStatus: (string) ($row['pilot_status'] ?? 'enrolled'),
            targetWorkerCount: (int) ($row['target_worker_count'] ?? 0),
            actualWorkerCount: (int) ($row['actual_worker_count'] ?? 0),
            industry: $row['industry'] ?? null,
            notes: $row['notes'] ?? null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }
}
