<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Infrastructure;

use WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository;
use WorkEddy\Modules\IAM\Domain\OrganizationMembership;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;
use Doctrine\DBAL\Connection;

final class OrganizationMembershipRepository implements IOrganizationMembershipRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function create(OrganizationMembership $membership): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $this->connection->insert('organization_memberships', [
            'uuid' => $membership->getUuid() !== '' ? $membership->getUuid() : UuidSupport::generate(),
            'user_id' => $membership->getUserId(),
            'organization_id' => $membership->getOrganizationId(),
            'role_id' => $membership->getRoleId(),
            'role_slug' => $membership->getRoleSlug(),
            'worksite_id' => $membership->getWorksiteId(),
            'department_id' => $membership->getDepartmentId(),
            'job_role_id' => $membership->getJobRoleId(),
            'status' => $membership->getStatus(),
            'is_primary' => $membership->isPrimary() ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(OrganizationMembership $membership): void
    {
        $this->connection->update('organization_memberships', [
            'role_id' => $membership->getRoleId(),
            'role_slug' => $membership->getRoleSlug(),
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], ['id' => (int) $membership->getId()]);
    }

    public function delete(string $uuid): void
    {
        $this->connection->update('organization_memberships', [
            'deleted_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            'status' => 'inactive',
        ], ['uuid' => $uuid]);
    }

    public function findByUuid(string $uuid): ?OrganizationMembership
    {
        $row = $this->connection->fetchAssociative(
            'SELECT om.*, o.uuid AS organization_uuid, o.name AS organization_name,
                    ws.uuid AS worksite_uuid, dept.uuid AS department_uuid, jr.uuid AS job_role_uuid
             FROM organization_memberships om
             INNER JOIN organizations o ON o.id = om.organization_id
             LEFT JOIN worksites ws ON ws.id = om.worksite_id
             LEFT JOIN departments dept ON dept.id = om.department_id
             LEFT JOIN job_roles jr ON jr.id = om.job_role_id
             WHERE om.uuid = :uuid AND om.deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $uuid],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findPrimaryByUserId(int|string $userId): ?OrganizationMembership
    {
        $row = $this->connection->fetchAssociative(
            'SELECT om.*, o.uuid AS organization_uuid, o.name AS organization_name,
                    ws.uuid AS worksite_uuid, dept.uuid AS department_uuid, jr.uuid AS job_role_uuid
             FROM organization_memberships om
             INNER JOIN organizations o ON o.id = om.organization_id
             LEFT JOIN worksites ws ON ws.id = om.worksite_id
             LEFT JOIN departments dept ON dept.id = om.department_id
             LEFT JOIN job_roles jr ON jr.id = om.job_role_id
             WHERE om.user_id = :userId AND om.deleted_at IS NULL AND om.status = :status
             ORDER BY om.is_primary DESC, om.id ASC
             LIMIT 1',
            ['userId' => (int) $userId, 'status' => 'active'],
        );

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByUserAndOrganizationUuid(int|string $userId, string $organizationUuid): ?OrganizationMembership
    {
        $row = $this->connection->fetchAssociative(
            'SELECT om.*, o.uuid AS organization_uuid, o.name AS organization_name,
                    ws.uuid AS worksite_uuid, dept.uuid AS department_uuid, jr.uuid AS job_role_uuid
             FROM organization_memberships om
             INNER JOIN organizations o ON o.id = om.organization_id
             LEFT JOIN worksites ws ON ws.id = om.worksite_id
             LEFT JOIN departments dept ON dept.id = om.department_id
             LEFT JOIN job_roles jr ON jr.id = om.job_role_id
             WHERE om.user_id = :userId
               AND o.uuid = :organizationUuid
               AND om.deleted_at IS NULL
               AND om.status = :status
             LIMIT 1',
            [
                'userId' => (int) $userId,
                'organizationUuid' => $organizationUuid,
                'status' => 'active',
            ],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('om.*', 'o.uuid AS organization_uuid', 'o.name AS organization_name', 'ws.uuid AS worksite_uuid', 'dept.uuid AS department_uuid', 'jr.uuid AS job_role_uuid')
            ->from('organization_memberships', 'om')
            ->innerJoin('om', 'organizations', 'o', 'o.id = om.organization_id')
            ->leftJoin('om', 'worksites', 'ws', 'ws.id = om.worksite_id')
            ->leftJoin('om', 'departments', 'dept', 'dept.id = om.department_id')
            ->leftJoin('om', 'job_roles', 'jr', 'jr.id = om.job_role_id')
            ->where('om.organization_id = :organizationId')
            ->andWhere('om.deleted_at IS NULL')
            ->setParameter('organizationId', $organizationId)
            ->orderBy('om.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn(array $row): OrganizationMembership => $this->hydrate($row), $rows);
    }

    public function findAllByUserId(int|string $userId): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('om.*', 'o.uuid AS organization_uuid', 'o.name AS organization_name', 'ws.uuid AS worksite_uuid', 'dept.uuid AS department_uuid', 'jr.uuid AS job_role_uuid')
            ->from('organization_memberships', 'om')
            ->innerJoin('om', 'organizations', 'o', 'o.id = om.organization_id')
            ->leftJoin('om', 'worksites', 'ws', 'ws.id = om.worksite_id')
            ->leftJoin('om', 'departments', 'dept', 'dept.id = om.department_id')
            ->leftJoin('om', 'job_roles', 'jr', 'jr.id = om.job_role_id')
            ->where('om.user_id = :userId')
            ->andWhere('om.deleted_at IS NULL')
            ->andWhere('om.status = :status')
            ->setParameter('userId', (int) $userId)
            ->setParameter('status', 'active')
            ->orderBy('om.is_primary', 'DESC')
            ->addOrderBy('om.id', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn(array $row): OrganizationMembership => $this->hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): OrganizationMembership
    {
        return new OrganizationMembership(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            userId: (int) $row['user_id'],
            organizationId: (int) $row['organization_id'],
            organizationUuid: $row['organization_uuid'] ?? null,
            organizationName: $row['organization_name'] ?? null,
            roleId: (int) $row['role_id'],
            roleSlug: (string) $row['role_slug'],
            worksiteId: isset($row['worksite_id']) ? (int) $row['worksite_id'] : null,
            worksiteUuid: $row['worksite_uuid'] ?? null,
            departmentId: isset($row['department_id']) ? (int) $row['department_id'] : null,
            departmentUuid: $row['department_uuid'] ?? null,
            jobRoleId: isset($row['job_role_id']) ? (int) $row['job_role_id'] : null,
            jobRoleUuid: $row['job_role_uuid'] ?? null,
            status: (string) ($row['status'] ?? 'active'),
            isPrimary: (bool) ($row['is_primary'] ?? true),
        );
    }
}
