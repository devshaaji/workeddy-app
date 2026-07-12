<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Session;

use Doctrine\DBAL\Connection;

final class DbUserSessionRepository implements IUserSessionRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function upsert(string $sessionId, int|string $userId, array $data): void
    {
        $existing = $this->connection->fetchOne(
            'SELECT session_id FROM iam_user_sessions WHERE session_id = :sessionId',
            ['sessionId' => $sessionId],
        );

        $payload = [
            'principal_id' => (string) $userId,
            'principal_type' => 'user',
            'ip_address' => isset($data['ip_address']) ? (string) $data['ip_address'] : null,
            'user_agent' => isset($data['user_agent']) ? (string) $data['user_agent'] : null,
            'started_at' => isset($data['login_at']) ? (string) $data['login_at'] : $this->now(),
            'last_activity_at' => isset($data['last_seen_at']) ? (string) $data['last_seen_at'] : $this->now(),
            'ended_at' => null,
            'revoked_at' => null,
            'updated_at' => $this->now(),
        ];

        if ($existing === false) {
            $payload['session_id'] = $sessionId;
            $payload['created_at'] = $payload['updated_at'];
            $this->connection->insert('iam_user_sessions', $payload);
            return;
        }

        $this->connection->update('iam_user_sessions', $payload, ['session_id' => $sessionId]);
    }

    public function touch(string $sessionId, string $lastSeenAt): void
    {
        $this->connection->executeStatement(
            'UPDATE iam_user_sessions
                SET last_activity_at = :lastSeenAt, updated_at = :lastSeenAt
              WHERE session_id = :sessionId AND revoked_at IS NULL',
            ['lastSeenAt' => $lastSeenAt, 'sessionId' => $sessionId],
        );
    }

    public function revoke(string $sessionId, int|string $actorId): void
    {
        $this->connection->update('iam_user_sessions', [
            'revoked_at' => $this->now(),
            'updated_at' => $this->now(),
        ], ['session_id' => $sessionId]);
    }

    public function findForSession(string $sessionId, int|string $userId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM iam_user_sessions WHERE session_id = :sessionId AND principal_id = :userId',
            ['sessionId' => $sessionId, 'userId' => $userId],
        );

        return $row !== false ? $row : null;
    }

    public function isActive(string $sessionId, int|string $userId): bool
    {
        $row = $this->findForSession($sessionId, $userId);

        return $row !== null && empty($row['revoked_at']);
    }

    public function listActiveForUser(int|string $userId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM iam_user_sessions WHERE principal_id = :userId AND revoked_at IS NULL AND ended_at IS NULL ORDER BY last_activity_at DESC, created_at DESC',
            ['userId' => $userId],
        );
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
