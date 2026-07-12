<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Session;

use WorkEddy\Modules\IAM\Settings\IAMSettings;
use WorkEddy\Platform\Cache\ICacheService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Exceptions\TooManyRequestsException;

final class SessionSecurityService
{
    private const STARTED_AT = 'session_started_at';
    private const LAST_ACTIVITY_AT = 'last_activity_at';
    private const LAST_HEARTBEAT_AT = 'last_heartbeat_at';
    private const HEARTBEAT_PREFIX = 'iam.session.heartbeat.';
    private const HEARTBEAT_MIN_INTERVAL_SECONDS = 30;
    private const SESSION_FINGERPRINT = 'session_fingerprint';

    public function __construct(
        private readonly ISessionService $session,
        private readonly IAMSettings $settings,
        private readonly ICacheService $cache,
        private readonly ?IUserSessionRepository $userSessions = null,
    ) {}

    public function enforce(Request $request): bool
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return false;
        }

        $status = $this->status($ctx);
        if ($status['expired']) {
            $this->session->destroy();
            return false;
        }

        // Validate session fingerprint (User-Agent binding)
        if (!$this->validateFingerprint($request)) {
            $this->session->destroy();
            return false;
        }

        if ($this->shouldTouchForRequest($request)) {
            $this->touchActivity($ctx, $request);
        }

        return true;
    }

    /**
     * @return array{
     *   expires_in:int,
     *   absolute_expires_in:int|null,
     *   absolute_timeout_enabled:bool,
     *   idle_timeout:int,
     *   warning_threshold:int,
     *   server_time:int,
     *   expired:bool
     * }
     */
    public function status(?UserContext $ctx = null): array
    {
        $ctx ??= $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Authentication required');
        }
        $this->assertCurrentSessionActive($ctx);

        $now = time();
        $startedAt = $this->startedAt($ctx, $now);
        $lastActivityAt = $this->lastActivityAt($now);
        $idleTimeout = $this->settings->sessionIdleTimeoutSeconds();
        $absoluteEnabled = $this->settings->sessionAbsoluteTimeoutEnabled();
        $absoluteTimeout = $this->settings->sessionAbsoluteTimeoutSeconds();
        $idleExpiresAt = $lastActivityAt + $idleTimeout;
        $absoluteExpiresAt = $absoluteEnabled ? $startedAt + $absoluteTimeout : null;
        $expiresAt = $absoluteExpiresAt !== null ? min($idleExpiresAt, $absoluteExpiresAt) : $idleExpiresAt;

        return [
            'expires_in' => max(0, $expiresAt - $now),
            'absolute_expires_in' => $absoluteExpiresAt !== null ? max(0, $absoluteExpiresAt - $now) : null,
            'absolute_timeout_enabled' => $absoluteEnabled,
            'idle_timeout' => $idleTimeout,
            'warning_threshold' => $this->settings->sessionWarningThresholdSeconds(),
            'server_time' => $now,
            'expired' => $now > $idleExpiresAt || ($absoluteExpiresAt !== null && $now > $absoluteExpiresAt),
        ];
    }

    /**
     * @return array<string, int|bool|null>
     */
    public function heartbeat(): array
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Authentication required');
        }

        $status = $this->status($ctx);
        if ($status['expired']) {
            $this->session->destroy();
            throw new AuthenticationException('Session expired');
        }

        $this->assertHeartbeatAllowed($ctx);
        $this->session->set(self::LAST_HEARTBEAT_AT, time());

        return $status;
    }

    /**
     * @return array<string, int|bool|null>
     */
    public function refreshActivity(Request $request): array
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Authentication required');
        }

        $status = $this->status($ctx);
        if ($status['expired']) {
            $this->session->destroy();
            throw new AuthenticationException('Session expired');
        }

        $this->touchActivity($ctx, $request);

        return $this->status($ctx);
    }

    public function touchActivity(UserContext $ctx, ?Request $request = null): void
    {
        $now = time();
        $this->session->set(self::LAST_ACTIVITY_AT, $now);
        $this->session->set(self::STARTED_AT, $this->startedAt($ctx, $now));

        // Store fingerprint if not yet set (first request after login)
        if ($this->session->get(self::SESSION_FINGERPRINT) === null && $request !== null) {
            $this->session->set(self::SESSION_FINGERPRINT, $this->computeFingerprint($request));
        }

        $sessionId = session_id();
        if ($sessionId !== '') {
            $this->userSessions?->touch($sessionId, (new \DateTimeImmutable('@' . $now))->format('Y-m-d H:i:s'));
        }
    }

    private function startedAt(UserContext $ctx, int $now): int
    {
        $startedAt = (int) $this->session->get(self::STARTED_AT, 0);
        if ($startedAt > 0) {
            return $startedAt;
        }

        $startedAt = $now;
        $this->session->set(self::STARTED_AT, $startedAt);
        $this->session->set(self::LAST_ACTIVITY_AT, $now);

        return $startedAt;
    }

    private function lastActivityAt(int $now): int
    {
        $lastActivityAt = (int) $this->session->get(self::LAST_ACTIVITY_AT, 0);
        if ($lastActivityAt > 0) {
            return $lastActivityAt;
        }

        $this->session->set(self::LAST_ACTIVITY_AT, $now);

        return $now;
    }

    private function shouldTouchForRequest(Request $request): bool
    {
        $uri = $request->getUri();
        if (
            str_starts_with($uri, '/api/v1/auth/session-status')
            || str_starts_with($uri, '/api/v1/auth/heartbeat')
            || str_starts_with($uri, '/api/v1/auth/session-activity')
        ) {
            return false;
        }

        if ($request->getMethod() !== 'GET') {
            return true;
        }

        $accept = strtolower((string) $request->header('accept', ''));

        return !str_contains($accept, 'application/json');
    }

    private function assertHeartbeatAllowed(UserContext $ctx): void
    {
        $sessionId = session_id();
        $keyPart = $sessionId !== '' ? hash('sha256', $sessionId) : 'user.' . $ctx->userId;
        $key = self::HEARTBEAT_PREFIX . $keyPart;
        $state = $this->cache->get($key, static fn(): array => [], self::HEARTBEAT_MIN_INTERVAL_SECONDS);
        $nextAllowedAt = is_array($state) ? (int) ($state['nextAllowedAt'] ?? 0) : 0;
        if ($nextAllowedAt > time()) {
            throw new TooManyRequestsException(sprintf(
                'Please wait %d second(s) before extending the session again.',
                max(1, $nextAllowedAt - time()),
            ));
        }

        $this->cache->set($key, ['nextAllowedAt' => time() + self::HEARTBEAT_MIN_INTERVAL_SECONDS], self::HEARTBEAT_MIN_INTERVAL_SECONDS);
    }

    private function assertCurrentSessionActive(UserContext $ctx): void
    {
        if ($this->userSessions === null) {
            return;
        }

        $sessionId = session_id();
        if ($sessionId === '') {
            return;
        }

        $row = $this->userSessions->findForSession($sessionId, $ctx->userId);
        if ($row !== null && !empty($row['revoked_at'])) {
            $this->session->destroy();
            throw new AuthenticationException('Session revoked');
        }
    }

    /**
     * Validate that the current request's fingerprint matches the stored session fingerprint.
     * Returns true if no fingerprint is stored yet (first request after login).
     */
    private function validateFingerprint(Request $request): bool
    {
        $stored = $this->session->get(self::SESSION_FINGERPRINT);
        if ($stored === null || $stored === '') {
            // First request — fingerprint will be stored in touchActivity()
            return true;
        }

        return hash_equals((string) $stored, $this->computeFingerprint($request));
    }

    /**
     * Compute a fingerprint from request attributes that should remain constant
     * across a session lifetime. Uses User-Agent as it doesn't change during a
     * normal browsing session, unlike IP which can change on mobile networks.
     */
    private function computeFingerprint(Request $request): string
    {
        $ua = trim((string) $request->header('User-Agent', ''));

        return hash('sha256', $ua);
    }
}
