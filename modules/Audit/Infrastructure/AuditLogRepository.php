<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Infrastructure;

use WorkEddy\Modules\Audit\Application\DTOs\AuditLogEntryDTO;
use WorkEddy\Modules\Audit\Application\DTOs\QueryAuditLogRequest;
use WorkEddy\Modules\Audit\Domain\Contracts\IAuditLogRepository;
use WorkEddy\Shared\Support\UuidSupport;
use Doctrine\DBAL\Connection;

final class AuditLogRepository implements IAuditLogRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function findByUuid(string $uuid): ?AuditLogEntryDTO
    {
        $row = $this->connection->fetchAssociative(
            "SELECT l.uuid, l.actor_id, actor_user.full_name AS actor_name, actor_user.email AS actor_username,
                    l.action, l.entity_type, l.entity_id, l.module, l.before_state, l.after_state, l.ip_address, l.created_at
             FROM audit_logs l
             LEFT JOIN users actor_user ON actor_user.id = l.actor_id
             WHERE l.uuid = :uuid
             LIMIT 1",
            ['uuid' => UuidSupport::requireValid($uuid)],
        );

        return $row === false ? null : $this->mapRow($row);
    }

    public function search(QueryAuditLogRequest $request): array
    {
        [$where, $params, $types] = $this->buildSearchQuery($request);

        $sql = "SELECT l.uuid, l.actor_id, actor_user.full_name AS actor_name, actor_user.email AS actor_username,
                       l.action, l.entity_type, l.entity_id, l.module, l.before_state, l.after_state, l.ip_address, l.created_at
                FROM audit_logs l
                LEFT JOIN users actor_user ON actor_user.id = l.actor_id
                WHERE " . $where . "
                ORDER BY l.created_at DESC
                LIMIT :limit OFFSET :offset";

        $params['limit'] = $request->limit;
        $params['offset'] = $request->offset;
        $types['limit'] = \PDO::PARAM_INT;
        $types['offset'] = \PDO::PARAM_INT;

        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);

        return array_map(fn(array $row): AuditLogEntryDTO => $this->mapRow($row), $rows);
    }

    public function count(QueryAuditLogRequest $request): int
    {
        [$where, $params, $types] = $this->buildSearchQuery($request);

        $sql = 'SELECT COUNT(*) FROM audit_logs l WHERE ' . $where;

        return (int) $this->connection->fetchOne($sql, $params, $types);
    }

    public function purgeOlderThan(string $cutoffDateTime): int
    {
        return $this->connection->executeStatement(
            'DELETE FROM audit_logs WHERE created_at < :cutoff',
            ['cutoff' => $cutoffDateTime],
        );
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, int>}
     */
    private function buildSearchQuery(QueryAuditLogRequest $request): array
    {
        $where = '1 = 1';
        $params = [];
        $types = [];

        if ($request->actorId !== null) {
            $where .= ' AND l.actor_id = :actorId';
            $params['actorId'] = $request->actorId;
        }

        if ($request->module !== null && trim($request->module) !== '') {
            $where .= ' AND l.module = :module';
            $params['module'] = trim($request->module);
        }

        if ($request->action !== null && trim($request->action) !== '') {
            $where .= ' AND l.action = :action';
            $params['action'] = trim($request->action);
        }

        if ($request->entityType !== null && trim($request->entityType) !== '') {
            $where .= ' AND l.entity_type = :entityType';
            $params['entityType'] = trim($request->entityType);
        }

        if ($request->entityId !== null && trim($request->entityId) !== '') {
            $where .= ' AND l.entity_id = :entityId';
            $params['entityId'] = trim($request->entityId);
        }

        if ($request->fromDate !== null) {
            $where .= ' AND l.created_at >= :fromDate';
            $params['fromDate'] = $request->fromDate;
        }

        if ($request->toDate !== null) {
            $where .= ' AND l.created_at <= :toDate';
            $params['toDate'] = $request->toDate;
        }

        return [$where, $params, $types];
    }

    private function mapRow(array $row): AuditLogEntryDTO
    {
        $actorIdRaw = $row['actor_id'];
        $actorId = is_numeric($actorIdRaw) ? (int)$actorIdRaw : (string)$actorIdRaw;

        return new AuditLogEntryDTO(
            id: (string) $row['uuid'],
            actorId: $actorId,
            action: (string) $row['action'],
            entityType: (string) $row['entity_type'],
            entityId: (string) $row['entity_id'],
            module: (string) $row['module'],
            before: $this->decodeJsonNullable($row['before_state'] ?? null),
            after: $this->decodeJsonNullable($row['after_state'] ?? null),
            ipAddress: $row['ip_address'] !== null ? (string) $row['ip_address'] : null,
            createdAt: (string) $row['created_at'],
            actorName: $row['actor_name'] !== null ? (string) $row['actor_name'] : null,
            actorUsername: $row['actor_username'] !== null ? (string) $row['actor_username'] : null,
        );
    }

    private function decodeJsonNullable(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
