<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Session;

interface IUserSessionRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActiveForUser(int|string $userId): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findForSession(string $sessionId, int|string $userId): ?array;

    /**
     * Update last_seen_at (activity timestamp) for a session.
     * Implementation is expected to use UPDATE WHERE session_id = ?.
     */
    public function touch(string $sessionId, string $lastSeenAt): void;

    /**
     * @param array<string, mixed> $data
     */
    public function upsert(string $sessionId, int|string $userId, array $data): void;

    public function revoke(string $sessionId, int|string $actorId): void;
}
