<?php

/**
 * User DBAL repository.
 *
 * Owns: users table.
 * Maps flat rows ↔ User domain entities.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Infrastructure;

use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Modules\IAM\Domain\UserStatus;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;
use Doctrine\DBAL\Connection;

final class UserRepository implements IUserRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function create(User $user): int|string
    {
        $nowStr = ($this->clock->now())->format('Y-m-d H:i:s');
        $uuid = $user->getUuid() ?: UuidSupport::generate();

        $this->connection->insert('users', [
            'uuid'          => $uuid,
            'email'         => $user->getEmail(),
            'full_name'     => $user->getFullName(),
            'password_hash' => $user->getPasswordHash(),
            'role_id'       => (int) $user->getRoleId(),
            'role_slug'     => $user->getRoleSlug(),
            'status'        => $user->getStatus()->value,
            'phone'         => $user->getPhone(),
            'authz_version' => $user->getAuthzVersion(),
            'created_at'    => $nowStr,
            'updated_at'    => $nowStr,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(User $user): void
    {
        $nowStr = ($this->clock->now())->format('Y-m-d H:i:s');

        $this->connection->update('users', [
            'email'         => $user->getEmail(),
            'full_name'     => $user->getFullName(),
            'password_hash' => $user->getPasswordHash(),
            'role_id'       => (int) $user->getRoleId(),
            'role_slug'     => $user->getRoleSlug(),
            'status'        => $user->getStatus()->value,
            'phone'         => $user->getPhone(),
            'last_login_at' => $user->getLastLoginAt(),
            'authz_version' => $user->getAuthzVersion(),
            'updated_at'    => $nowStr,
        ], ['id' => (int) $user->getId()]);
    }

    public function findById(int|string $id): ?User
    {
        // If string UUID is passed (e.g. legacy/fallback), route to findByUuid
        if (is_string($id) && !is_numeric($id)) {
            return $this->findByUuid($id);
        }

        $row = $this->connection->fetchAssociative(
            $this->baseSelectSql() . ' WHERE u.id = ?',
            [(int) $id]
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function findByUuid(string $uuid): ?User
    {
        $row = $this->connection->fetchAssociative(
            $this->baseSelectSql() . ' WHERE u.uuid = ?',
            [$uuid]
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->connection->fetchAssociative(
            $this->baseSelectSql() . ' WHERE LOWER(u.email) = LOWER(?)',
            [$email]
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $membershipJoin = $this->membershipJoinCondition($filters['organization_uuid'] ?? null);
        $qb = $this->connection->createQueryBuilder()
            ->select(
                'u.*',
                'COALESCE(up.full_name, u.full_name) AS effective_full_name',
                'COALESCE(up.phone, u.phone) AS effective_phone',
                'om.role_id AS membership_role_id',
                'om.role_slug AS membership_role_slug',
                'om.id AS membership_id',
                'om.uuid AS membership_uuid',
                'org.id AS organization_id',
                'org.uuid AS organization_uuid',
                'org.name AS organization_name',
                'ws.id AS worksite_id',
                'ws.uuid AS worksite_uuid',
                'dept.id AS department_id',
                'dept.uuid AS department_uuid',
                'jr.id AS job_role_id',
                'jr.uuid AS job_role_uuid'
            )
            ->from('users', 'u')
            ->leftJoin('u', 'user_profiles', 'up', 'up.user_id = u.id')
            ->leftJoin('u', 'organization_memberships', 'om', $membershipJoin)
            ->leftJoin('om', 'organizations', 'org', 'org.id = om.organization_id')
            ->leftJoin('om', 'worksites', 'ws', 'ws.id = om.worksite_id')
            ->leftJoin('om', 'departments', 'dept', 'dept.id = om.department_id')
            ->leftJoin('om', 'job_roles', 'jr', 'jr.id = om.job_role_id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('u.id', 'DESC');

        if (isset($filters['status'])) {
            $qb->andWhere('u.status = :status')->setParameter('status', $filters['status']);
        } else {
            $qb->andWhere('u.status <> :deleted_status')->setParameter('deleted_status', UserStatus::DELETED->value);
        }
        if (isset($filters['role_slug'])) {
            $qb->andWhere('COALESCE(om.role_slug, u.role_slug) = :role_slug')->setParameter('role_slug', $filters['role_slug']);
        }
        if (isset($filters['search'])) {
            $qb->andWhere('(COALESCE(up.full_name, u.full_name) LIKE :search OR u.email LIKE :search)')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }
        if (isset($filters['organization_uuid'])) {
            $qb->andWhere('org.uuid = :organization_uuid')->setParameter('organization_uuid', $filters['organization_uuid']);
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();
        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    public function count(array $filters = []): int
    {
        $membershipJoin = $this->membershipJoinCondition($filters['organization_uuid'] ?? null);
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('users', 'u')
            ->leftJoin('u', 'user_profiles', 'up', 'up.user_id = u.id')
            ->leftJoin('u', 'organization_memberships', 'om', $membershipJoin)
            ->leftJoin('om', 'organizations', 'org', 'org.id = om.organization_id');

        if (isset($filters['status'])) {
            $qb->andWhere('u.status = :status')->setParameter('status', $filters['status']);
        } else {
            $qb->andWhere('u.status <> :deleted_status')->setParameter('deleted_status', UserStatus::DELETED->value);
        }
        if (isset($filters['role_slug'])) {
            $qb->andWhere('COALESCE(om.role_slug, u.role_slug) = :role_slug')->setParameter('role_slug', $filters['role_slug']);
        }
        if (isset($filters['search'])) {
            $qb->andWhere('(COALESCE(up.full_name, u.full_name) LIKE :search OR u.email LIKE :search)')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }
        if (isset($filters['organization_uuid'])) {
            $qb->andWhere('org.uuid = :organization_uuid')->setParameter('organization_uuid', $filters['organization_uuid']);
        }

        return (int) $qb->executeQuery()->fetchOne();
    }

    public function countByRoleIds(array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn(int $id): bool => $id > 0)));
        if ($roleIds === []) {
            return [];
        }

        $rows = $this->connection->createQueryBuilder()
            ->select('COALESCE(om.role_id, u.role_id) AS effective_role_id', 'COUNT(*) AS user_count')
            ->from('users', 'u')
            ->leftJoin('u', 'organization_memberships', 'om', 'om.user_id = u.id AND om.deleted_at IS NULL AND om.status = \'active\' AND om.is_primary = 1')
            ->where('COALESCE(om.role_id, u.role_id) IN (:role_ids)')
            ->andWhere('u.status <> :deleted_status')
            ->groupBy('effective_role_id')
            ->setParameter('role_ids', $roleIds, Connection::PARAM_INT_ARRAY)
            ->setParameter('deleted_status', UserStatus::DELETED->value)
            ->executeQuery()
            ->fetchAllAssociative();

        $counts = array_fill_keys($roleIds, 0);
        foreach ($rows as $row) {
            $counts[(int) $row['effective_role_id']] = (int) $row['user_count'];
        }

        return $counts;
    }

    public function findByRoleId(int|string $roleId, int $limit = 25): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select(
                'u.*',
                'COALESCE(up.full_name, u.full_name) AS effective_full_name',
                'COALESCE(up.phone, u.phone) AS effective_phone',
                'om.role_id AS membership_role_id',
                'om.role_slug AS membership_role_slug',
                'om.id AS membership_id',
                'om.uuid AS membership_uuid',
                'org.id AS organization_id',
                'org.uuid AS organization_uuid',
                'org.name AS organization_name',
                'ws.id AS worksite_id',
                'ws.uuid AS worksite_uuid',
                'dept.id AS department_id',
                'dept.uuid AS department_uuid',
                'jr.id AS job_role_id',
                'jr.uuid AS job_role_uuid'
            )
            ->from('users', 'u')
            ->leftJoin('u', 'user_profiles', 'up', 'up.user_id = u.id')
            ->leftJoin('u', 'organization_memberships', 'om', 'om.user_id = u.id AND om.deleted_at IS NULL AND om.status = \'active\' AND om.is_primary = 1')
            ->leftJoin('om', 'organizations', 'org', 'org.id = om.organization_id')
            ->leftJoin('om', 'worksites', 'ws', 'ws.id = om.worksite_id')
            ->leftJoin('om', 'departments', 'dept', 'dept.id = om.department_id')
            ->leftJoin('om', 'job_roles', 'jr', 'jr.id = om.job_role_id')
            ->where('COALESCE(om.role_id, u.role_id) = :role_id')
            ->andWhere('u.status <> :deleted_status')
            ->setParameter('role_id', (int) $roleId)
            ->setParameter('deleted_status', UserStatus::DELETED->value)
            ->setMaxResults(max(1, min(100, $limit)))
            ->orderBy('u.full_name', 'ASC')
            ->addOrderBy('u.id', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    public function bumpAuthzVersion(int|string $userId): int
    {
        $this->connection->executeStatement(
            'UPDATE users SET authz_version = authz_version + 1, updated_at = :updatedAt WHERE id = :id',
            [
                'updatedAt' => ($this->clock->now())->format('Y-m-d H:i:s'),
                'id' => (int) $userId,
            ],
        );

        return (int) $this->connection->fetchOne('SELECT authz_version FROM users WHERE id = ?', [(int) $userId]);
    }

    private function hydrate(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            email: $row['email'],
            fullName: $row['effective_full_name'] ?? $row['full_name'],
            passwordHash: $row['password_hash'],
            roleId: (int) $row['role_id'],
            roleSlug: (string) $row['role_slug'],
            status: UserStatus::from($row['status']),
            phone: $row['effective_phone'] ?? $row['phone'] ?? null,
            lastLoginAt: $row['last_login_at'] ?? null,
            authzVersion: isset($row['authz_version']) ? (int) $row['authz_version'] : 1,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
            organizationId: isset($row['organization_id']) ? (int) $row['organization_id'] : null,
            organizationUuid: $row['organization_uuid'] ?? null,
            organizationName: $row['organization_name'] ?? null,
            membershipId: isset($row['membership_id']) ? (int) $row['membership_id'] : null,
            membershipUuid: $row['membership_uuid'] ?? null,
            membershipRoleId: isset($row['membership_role_id']) ? (int) $row['membership_role_id'] : null,
            membershipRoleSlug: $row['membership_role_slug'] ?? null,
            worksiteId: isset($row['worksite_id']) ? (int) $row['worksite_id'] : null,
            worksiteUuid: $row['worksite_uuid'] ?? null,
            departmentId: isset($row['department_id']) ? (int) $row['department_id'] : null,
            departmentUuid: $row['department_uuid'] ?? null,
            jobRoleId: isset($row['job_role_id']) ? (int) $row['job_role_id'] : null,
            jobRoleUuid: $row['job_role_uuid'] ?? null,
        );
    }

    private function baseSelectSql(): string
    {
        return 'SELECT u.*,
                       COALESCE(up.full_name, u.full_name) AS effective_full_name,
                       COALESCE(up.phone, u.phone) AS effective_phone,
                       om.role_id AS membership_role_id,
                       om.role_slug AS membership_role_slug,
                       om.id AS membership_id,
                       om.uuid AS membership_uuid,
                       org.id AS organization_id,
                       org.uuid AS organization_uuid,
                       org.name AS organization_name,
                       ws.id AS worksite_id,
                       ws.uuid AS worksite_uuid,
                       dept.id AS department_id,
                       dept.uuid AS department_uuid,
                       jr.id AS job_role_id,
                       jr.uuid AS job_role_uuid
                FROM users u
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN organization_memberships om ON om.user_id = u.id AND om.deleted_at IS NULL AND om.status = \'active\' AND om.is_primary = 1
                LEFT JOIN organizations org ON org.id = om.organization_id
                LEFT JOIN worksites ws ON ws.id = om.worksite_id
                LEFT JOIN departments dept ON dept.id = om.department_id
                LEFT JOIN job_roles jr ON jr.id = om.job_role_id';
    }

    private function membershipJoinCondition(?string $organizationUuid = null): string
    {
        $base = 'om.user_id = u.id AND om.deleted_at IS NULL AND om.status = \'active\'';
        if ($organizationUuid !== null && trim($organizationUuid) !== '') {
            return $base;
        }

        return $base . ' AND om.is_primary = 1';
    }
}
