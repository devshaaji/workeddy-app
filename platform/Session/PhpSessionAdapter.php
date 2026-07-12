<?php

/**
 * PHP native session adapter — implements ISessionService.
 *
 * The ONLY place that touches $_SESSION directly.
 * All other code accesses session through ISessionService.
 */

declare(strict_types=1);

namespace WorkEddy\Platform\Session;

use WorkEddy\Modules\IAM\Settings\IAMSettings;

final class PhpSessionAdapter implements ISessionService
{
    public function __construct(
        private readonly IAMSettings $settings,
        private readonly ?IUserSessionRepository $userSessions = null,
    ) {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_httponly', '1');

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $this->secureCookie(),
                'httponly' => true,
                'samesite' => $this->sameSiteCookie(),
            ]);
            session_start();
        }
    }

    public function getUserId(): int|string|null
    {
        return $_SESSION['USER'] ?? null;
    }

    public function getRoleType(): ?string
    {
        return $_SESSION['ROLE_TYPE'] ?? null;
    }

    public function getPrivileges(): array
    {
        return $_SESSION['privileges'] ?? [];
    }

    public function isAuthenticated(): bool
    {
        return $this->getUserContext() !== null || $this->getUserId() !== null;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $sessionId = session_id();
        $ctx = $this->getUserContext();
        if ($sessionId !== '') {
            $this->userSessions?->revoke($sessionId, $ctx?->userId);
        }
        $this->clearAuthenticationState();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            session_destroy();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => (string) ($params['samesite'] ?? 'Lax'),
            ]);
        }
    }

    public function setUserContext(UserContext $context): void
    {
        $_SESSION['v2_user_context'] = json_encode($this->userContextToArray($context), JSON_THROW_ON_ERROR);
        $_SESSION['USER'] = $context->userId;
        $_SESSION['ROLE_TYPE'] = $context->roleType;
        $_SESSION['privileges'] = $context->privileges;
        $_SESSION['AUTHZ_VERSION'] = $context->authzVersion;
        $_SESSION['session_started_at'] = strtotime($context->loginAt) ?: time();
        $_SESSION['last_activity_at'] = time();
        $_SESSION['last_heartbeat_at'] = null;

        $sessionId = session_id();
        if ($sessionId !== '') {
            $this->userSessions?->upsert($sessionId, $context->userId, [
                'role_type' => $context->roleType,
                'authz_version' => $context->authzVersion,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'login_at' => $this->normalizeLoginAt($context->loginAt),
                'last_seen_at' => $this->now(),
            ]);
        }
    }

    public function getUserContext(): ?UserContext
    {
        if (!isset($_SESSION['v2_user_context'])) {
            return null;
        }

        $context = $this->hydrateUserContext((string) $_SESSION['v2_user_context']);
        if ($context === null) {
            return null;
        }

        $sessionId = session_id();
        if ($sessionId === '') {
            $this->clearAuthenticationState();
            return null;
        }

        if ($this->userSessions !== null) {
            $row = $this->userSessions->findForSession($sessionId, $context->userId);
            if ($row !== null && !empty($row['revoked_at'])) {
                $this->clearAuthenticationState();
                return null;
            }

            if ($row !== null && !$this->sessionFingerprintMatches($row)) {
                $this->userSessions->revoke($sessionId, $context->userId);
                $this->clearAuthenticationState();
                return null;
            }

            if ($row === null) {
                $this->upsertCurrentSession($sessionId, $context);
            }
        }

        return $context;
    }

    private function normalizeLoginAt(string $loginAt): string
    {
        try {
            return (new \DateTimeImmutable($loginAt))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $this->now();
        }
    }

    private function upsertCurrentSession(string $sessionId, UserContext $context): void
    {
        $this->userSessions?->upsert($sessionId, $context->userId, [
            'role_type' => $context->roleType,
            'authz_version' => $context->authzVersion,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'login_at' => $this->normalizeLoginAt($context->loginAt),
            'last_seen_at' => $this->now(),
        ]);
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function sessionFingerprintMatches(array $row): bool
    {
        if ($this->bindSessionIp()) {
            $expectedIp = (string) ($row['ip_address'] ?? '');
            $currentIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            if ($expectedIp !== '' && $currentIp !== '' && !hash_equals($expectedIp, $currentIp)) {
                return false;
            }
        }

        if ($this->bindSessionUserAgent()) {
            $expectedAgent = (string) ($row['user_agent'] ?? '');
            $currentAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            if ($expectedAgent !== '' && $currentAgent !== '' && !hash_equals($expectedAgent, $currentAgent)) {
                return false;
            }
        }

        return true;
    }

    private function clearAuthenticationState(): void
    {
        unset(
            $_SESSION['pending_auth'],
            $_SESSION['v1_user_context'],
            $_SESSION['USER'],
            $_SESSION['ROLE_TYPE'],
            $_SESSION['privileges'],
            $_SESSION['AUTHZ_VERSION'],
            $_SESSION['session_started_at'],
            $_SESSION['last_activity_at'],
            $_SESSION['last_heartbeat_at'],
        );
    }

    private function secureCookie(): bool
    {
        $configured = filter_var($_ENV['SESSION_SECURE'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($configured !== null) {
            return $configured;
        }

        return isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }

    private function sameSiteCookie(): string
    {
        $sameSite = ucfirst(strtolower((string) ($_ENV['SESSION_SAMESITE'] ?? 'Lax')));

        return in_array($sameSite, ['Lax', 'Strict', 'None'], true) ? $sameSite : 'Lax';
    }

    private function bindSessionIp(): bool
    {
        return filter_var($_ENV['SESSION_BIND_IP'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    private function bindSessionUserAgent(): bool
    {
        return filter_var($_ENV['SESSION_BIND_USER_AGENT'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Serialize UserContext to a JSON-safe array.
     * Eliminates PHP Object Injection risk by avoiding serialize().
     *
     * @return array<string, mixed>
     */
    private function userContextToArray(UserContext $context): array
    {
        return [
            'tenantId' => $context->tenantId,
            'userId' => $context->userId,
            'roleId' => $context->roleId,
            'organizationId' => $context->organizationId,
            'organizationUuid' => $context->organizationUuid,
            'membershipId' => $context->membershipId,
            'membershipUuid' => $context->membershipUuid,
            'platformRoleId' => $context->platformRoleId,
            'platformRoleType' => $context->platformRoleType,
            'membershipRoleId' => $context->membershipRoleId,
            'membershipRoleType' => $context->membershipRoleType,
            'roleType' => $context->roleType,
            'privileges' => $context->privileges,
            'loginAt' => $context->loginAt,
            'authzVersion' => $context->authzVersion,
        ];
    }

    /**
     * Reconstruct UserContext from JSON string.
     * Returns null if data is malformed or uses the old serialize() format,
     * which forces re-authentication.
     */
    private function hydrateUserContext(string $raw): ?UserContext
    {
        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Old serialized format or corrupt data — force re-login
            $this->clearAuthenticationState();
            return null;
        }

        if (!is_array($data) || !isset($data['userId'], $data['roleType'], $data['privileges'], $data['loginAt'])) {
            $this->clearAuthenticationState();
            return null;
        }

        return new UserContext(
            tenantId: (string) ($data['tenantId'] ?? 'platform'),
            userId: $data['userId'],
            roleId: (int) ($data['roleId'] ?? 0),
            organizationId: isset($data['organizationId']) ? (int) $data['organizationId'] : null,
            organizationUuid: isset($data['organizationUuid']) ? (string) $data['organizationUuid'] : null,
            membershipId: isset($data['membershipId']) ? (int) $data['membershipId'] : null,
            membershipUuid: isset($data['membershipUuid']) ? (string) $data['membershipUuid'] : null,
            platformRoleId: (int) ($data['platformRoleId'] ?? 0),
            platformRoleType: isset($data['platformRoleType']) ? (string) $data['platformRoleType'] : null,
            membershipRoleId: isset($data['membershipRoleId']) ? (int) $data['membershipRoleId'] : null,
            membershipRoleType: isset($data['membershipRoleType']) ? (string) $data['membershipRoleType'] : null,
            roleType: (string) $data['roleType'],
            privileges: (array) $data['privileges'],
            loginAt: (string) $data['loginAt'],
            authzVersion: (int) ($data['authzVersion'] ?? 1),
        );
    }
}
