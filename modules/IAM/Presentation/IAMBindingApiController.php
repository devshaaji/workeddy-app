<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Presentation;

use WorkEddy\Modules\IAM\Application\ChangePasswordUseCase;
use WorkEddy\Modules\IAM\Application\DTOs\ChangePasswordRequest;
use WorkEddy\Modules\IAM\Application\SwitchTenantUseCase;
use WorkEddy\Modules\Audit\Application\DTOs\AuditLogEntryDTO;
use WorkEddy\Modules\Audit\Application\DTOs\QueryAuditLogRequest;
use WorkEddy\Modules\Audit\Application\QueryAuditLogUseCase;
use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\Permission;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\IUserSessionRepository;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;

final class IAMBindingApiController
{
    public function __construct(
        private readonly IUserRepository $users,
        private readonly IOrganizationMembershipRepository $memberships,
        private readonly IRoleRepository $roles,
        private readonly IPermissionRepository $permissionRepository,
        private readonly ChangePasswordUseCase $changePassword,
        private readonly SwitchTenantUseCase $switchTenant,
        private readonly IPermissionService $permissions,
        private readonly ISessionService $session,
        private readonly IUserSessionRepository $userSessions,
        private readonly QueryAuditLogUseCase $auditLog,
        private readonly IAMUserActionPolicy $userActionPolicy,
        private readonly IClock $clock,
    ) {}

    public function pendingApprovals(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, IAMPermissions::USER_VIEW);

        $filters = ['status' => 'pending'];
        if ($ctx->organizationUuid !== null && $ctx->organizationUuid !== '') {
            $filters['organization_uuid'] = $ctx->organizationUuid;
        }
        $limit = max(1, min(100, (int) ($request->query('limit') ?? 50)));
        $offset = max(0, (int) ($request->query('offset') ?? 0));

        return Response::json([
            'status' => 'ok',
            'data' => array_map(function (\WorkEddy\Modules\IAM\Domain\User $user) use ($ctx): array {
                return array_merge($this->serializeUser($user, includePermissions: false, organizationUuid: $ctx->organizationUuid), [
                    'actions' => $this->userActionPolicy->tableActions($ctx, $user),
                ]);
            }, $this->users->findAll($filters, $limit, $offset)),
            'meta' => ['total' => $this->users->count($filters), 'limit' => $limit, 'offset' => $offset],
        ]);
    }

    public function userPermissions(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, IAMPermissions::PERMISSION_ASSIGN);

        $user = $this->requireUser((string) ($request->routeParam('id')), $ctx);

        $body = $this->requestData($request);
        if ($body !== []) {
            $grantIds = $body['grantPermissionIds'] ?? [];
            $denyIds = $body['denyPermissionIds'] ?? [];
            if (!is_array($grantIds) || !is_array($denyIds)) {
                throw new ValidationException([
                    'permissions' => 'grantPermissionIds and denyPermissionIds must be arrays.',
                ]);
            }

            $grantIds = $this->resolvePermissionIds($grantIds);
            $denyIds = $this->resolvePermissionIds($denyIds);
            foreach (array_merge($grantIds, $denyIds) as $permissionId) {
                if ($permissionId <= 0 || $this->permissionRepository->findById($permissionId) === null) {
                    throw new ValidationException(['permissions' => 'One or more permission IDs are invalid.']);
                }
            }

            $this->permissionRepository->replaceUserPermissionOverrides($user->getId(), $grantIds, $denyIds, $ctx->userId);
        }

        $roleId = $user->getMembershipRoleId() ?? (int) $user->getRoleId();
        $effectiveSlugs = $this->permissionRepository->resolveEffectivePermissions($user->getId(), $roleId);
        $role = $this->roles->findById($roleId);
        $rolePermissionSlugs = $role?->getPermissions() ?? [];
        $overrides = $this->permissionRepository->listUserPermissionOverrides($user->getId());
        $permissionMap = $this->permissionMap();

        return Response::json([
            'status' => 'ok',
            'data' => [
                'userId' => $user->getUuid(),
                'effectivePermissionRows' => $this->permissionRows($effectiveSlugs, $permissionMap, $rolePermissionSlugs, $overrides),
                'overrides' => $this->overrideRows($overrides, $permissionMap, $rolePermissionSlugs, $effectiveSlugs),
            ],
        ]);
    }

    public function profile(Request $request): Response
    {
        $ctx = $this->requireContext();
        $user = $this->users->findById($ctx->userId);
        if ($user === null) {
            throw new AuthenticationException('Session user no longer exists.');
        }

        return Response::json([
            'status' => 'ok',
            'data' => array_merge(
                $this->serializeUser($user, includePermissions: true, organizationUuid: $ctx->organizationUuid),
                [
                    'activeTenant' => [
                        'id' => $ctx->tenantId,
                        'organizationUuid' => $ctx->organizationUuid,
                        'membershipId' => $ctx->membershipUuid,
                        'roleSlug' => $ctx->roleType,
                        'platformRoleSlug' => $ctx->platformRoleType,
                    ],
                    'tenants' => $this->serializeTenantOptions((int) $ctx->userId),
                ],
            ),
        ]);
    }

    public function switchTenant(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = $this->requestData($request);
        $tenantId = (string) ($body['tenantId'] ?? $body['tenant_id'] ?? $body['organizationUuid'] ?? $body['organization_uuid'] ?? '');

        $updated = $this->switchTenant->execute($tenantId, $ctx);

        return Response::json([
            'status' => 'ok',
            'data' => [
                'tenantId' => $updated->tenantId,
                'organizationUuid' => $updated->organizationUuid,
                'membershipId' => $updated->membershipUuid,
                'roleSlug' => $updated->roleType,
                'platformRoleSlug' => $updated->platformRoleType,
                'privileges' => $updated->privileges,
            ],
        ]);
    }

    public function updateProfilePassword(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = $this->requestData($request);

        $this->changePassword->execute(new ChangePasswordRequest(
            userId: $ctx->userId,
            currentPassword: (string) ($body['currentPassword'] ?? $body['current_password'] ?? ''),
            newPassword: (string) ($body['newPassword'] ?? $body['new_password'] ?? $body['password'] ?? ''),
        ), $ctx);

        return Response::json(['status' => 'ok']);
    }

    public function profileSessions(Request $request): Response
    {
        $ctx = $this->requireContext();
        $currentUser = $this->users->findById($ctx->userId);
        if ($currentUser === null) {
            throw new AuthenticationException('Session user no longer exists.');
        }
        $currentSessionId = session_id();
        if (!$this->ensureCurrentSessionRow($ctx, $currentSessionId)) {
            throw new AuthenticationException('Unauthenticated');
        }
        $rows = $this->userSessions->listActiveForUser($ctx->userId);
        if ($rows === []) {
            $rows[] = [
                'session_id' => $currentSessionId !== '' ? $currentSessionId : null,
                'user_id' => $ctx->userId,
                'role_type' => $ctx->roleType,
                'authz_version' => $ctx->authzVersion,
                'ip_address' => null,
                'user_agent' => null,
                'login_at' => $ctx->loginAt,
                'last_seen_at' => $ctx->loginAt,
            ];
        }

        return Response::json([
            'status' => 'ok',
            'data' => array_map(function (array $row) use ($ctx, $currentSessionId, $currentUser): array {
                return [
                    'sessionId' => isset($row['session_id']) ? (string) $row['session_id'] : null,
                    'userId' => $currentUser->getUuid(),
                    'loginAt' => isset($row['login_at']) ? (string) $row['login_at'] : $ctx->loginAt,
                    'lastSeenAt' => isset($row['last_seen_at']) ? (string) $row['last_seen_at'] : $ctx->loginAt,
                    'ipAddress' => isset($row['ip_address']) ? (string) $row['ip_address'] : null,
                    'userAgent' => isset($row['user_agent']) ? (string) $row['user_agent'] : null,
                    'current' => isset($row['session_id']) && (string) $row['session_id'] === $currentSessionId,
                ];
            }, $rows),
        ]);
    }

    public function profileActivity(Request $request): Response
    {
        $ctx = $this->requireContext();
        $limit = max(1, min(25, (int) ($request->query('limit') ?? 5)));
        $offset = max(0, (int) ($request->query('offset') ?? 0));
        $query = new QueryAuditLogRequest(
            actorId: $ctx->userId,
            limit: $limit,
            offset: $offset,
        );

        return Response::json([
            'status' => 'ok',
            'data' => array_map([$this, 'serializeProfileActivity'], $this->auditLog->execute($query)),
            'meta' => [
                'total' => $this->auditLog->count($query),
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    public function revokeProfileSession(Request $request): Response
    {
        $ctx = $this->requireContext();
        $targetSessionId = trim((string) ($request->routeParam('sessionId', '')));
        if ($targetSessionId === '') {
            throw new ValidationException(['sessionId' => 'sessionId is required.']);
        }

        $found = false;
        foreach ($this->userSessions->listActiveForUser($ctx->userId) as $row) {
            if ((string) ($row['session_id'] ?? '') === $targetSessionId) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new NotFoundException('Session ' . $targetSessionId . ' not found');
        }

        $this->userSessions->revoke($targetSessionId, $ctx->userId);
        if ($targetSessionId === session_id()) {
            $this->session->destroy();
        }

        return Response::json(['status' => 'ok']);
    }

    public function userSessions(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, IAMPermissions::USER_VIEW);

        $user = $this->requireUser((string) ($request->routeParam('id', '')), $ctx);

        $rows = $this->userSessions->listActiveForUser($user->getId());
        $markCurrent = $user->getId() === $ctx->userId;
        if ($markCurrent) {
            if (!$this->ensureCurrentSessionRow($ctx, session_id())) {
                throw new AuthenticationException('Unauthenticated');
            }
            $rows = $this->userSessions->listActiveForUser($user->getId());
        }
        if ($rows === [] && $markCurrent) {
            $rows[] = $this->currentSessionFallbackRow($ctx, session_id());
        }

        return Response::json([
            'status' => 'ok',
            'data' => $this->serializeSessions(
                $rows,
                $user->getUuid(),
                session_id(),
                $markCurrent,
            ),
        ]);
    }

    public function revokeUserSession(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, IAMPermissions::USER_PASSWORD_RESET);

        $user = $this->requireUser((string) ($request->routeParam('id', '')), $ctx);

        $targetSessionId = trim((string) ($request->routeParam('sessionId', '')));
        if ($targetSessionId === '') {
            throw new ValidationException(['sessionId' => 'sessionId is required.']);
        }

        $found = false;
        foreach ($this->userSessions->listActiveForUser($user->getId()) as $row) {
            if ((string) ($row['session_id'] ?? '') === $targetSessionId) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new NotFoundException('Session ' . $targetSessionId . ' not found');
        }

        $this->userSessions->revoke($targetSessionId, $ctx->userId);

        return Response::json(['status' => 'ok']);
    }

    private function requireContext(): \WorkEddy\Platform\Session\UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }

    private function serializeUser(\WorkEddy\Modules\IAM\Domain\User $user, bool $includePermissions = false, ?string $organizationUuid = null): array
    {
        $membership = null;
        if ($organizationUuid !== null && trim($organizationUuid) !== '') {
            $membership = $this->memberships->findByUserAndOrganizationUuid((int) $user->getId(), $organizationUuid);
        }

        $data = [
            'uuid' => $user->getUuid(),
            'email' => $user->getEmail(),
            'profile' => [
                'fullName' => $user->getFullName(),
                'phone' => $user->getPhone(),
                'status' => $user->getStatus()->value,
                'lastLoginAt' => $user->getLastLoginAt(),
                'createdAt' => $user->getCreatedAt(),
            ],
            'membership' => [
                'id' => $membership?->getUuid() ?? $user->getMembershipUuid(),
                'organizationUuid' => $membership?->getOrganizationUuid() ?? $user->getOrganizationUuid(),
                'organizationName' => $membership?->getOrganizationName() ?? $user->getOrganizationName(),
                'roleSlug' => $membership?->getRoleSlug() ?? $user->getMembershipRoleSlug() ?? $user->getRoleSlug(),
                'worksiteUuid' => $membership?->getWorksiteUuid() ?? $user->getWorksiteUuid(),
                'departmentUuid' => $membership?->getDepartmentUuid() ?? $user->getDepartmentUuid(),
                'jobRoleUuid' => $membership?->getJobRoleUuid() ?? $user->getJobRoleUuid(),
                'platformRoleSlug' => $user->getRoleSlug(),
            ],
        ];

        if ($includePermissions) {
            $roleId = $membership?->getRoleId() ?? $user->getMembershipRoleId() ?? (int) $user->getRoleId();
            $roleSlug = $membership?->getRoleSlug() ?? $user->getMembershipRoleSlug() ?? $user->getRoleSlug();
            $role = $this->roles->findById($roleId);
            $data['membership']['roleName'] = $role?->getName() ?? $roleSlug;
            $data['effectivePermissions'] = $this->permissionRepository->resolveEffectivePermissions(
                $user->getId(),
                $roleId,
            );
            $data['authzVersion'] = $user->getAuthzVersion();
        }

        return $data;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function serializeSessions(array $rows, string $userId, string $currentSessionId, bool $markCurrent): array
    {
        return array_map(function (array $row) use ($userId, $currentSessionId, $markCurrent): array {
            $sessionId = isset($row['session_id']) ? (string) $row['session_id'] : null;

            return [
                'sessionId' => $sessionId,
                'userId' => $userId,
                'loginAt' => isset($row['login_at']) ? (string) $row['login_at'] : null,
                'lastSeenAt' => isset($row['last_seen_at']) ? (string) $row['last_seen_at'] : null,
                'ipAddress' => isset($row['ip_address']) ? (string) $row['ip_address'] : null,
                'userAgent' => isset($row['user_agent']) ? (string) $row['user_agent'] : null,
                'current' => $markCurrent && ($sessionId === $currentSessionId || ($sessionId === null && $currentSessionId === '')),
            ];
        }, $rows);
    }

    private function serializeProfileActivity(AuditLogEntryDTO $entry): array
    {
        return [
            'action' => $this->humanizeAuditAction($entry->action),
            'description' => $this->describeAuditActivity($entry),
            'location' => $entry->ipAddress,
            'os' => null,
            'createdAt' => $entry->createdAt,
        ];
    }

    private function humanizeAuditAction(string $action): string
    {
        $label = trim((string) preg_replace('/\s+/', ' ', str_replace(['.', '_', '-'], ' ', $action)));

        return $label !== '' ? str_replace('Iam ', 'IAM ', ucwords($label)) : 'Activity';
    }

    private function describeAuditActivity(AuditLogEntryDTO $entry): string
    {
        $module = trim($entry->module) !== '' ? trim($entry->module) : 'System';
        $entityType = trim($entry->entityType);

        return $entityType !== '' ? $module . ' ' . $entityType : $module . ' activity';
    }

    /**
     * @return array<string, Permission>
     */
    private function permissionMap(): array
    {
        $map = [];
        foreach ($this->permissionRepository->findAll() as $permission) {
            $map[$permission->slug] = $permission;
        }

        return $map;
    }

    /**
     * @param string[] $slugs
     * @param array<string, Permission> $permissionMap
     * @param string[] $rolePermissionSlugs
     * @param array<int, array{permissionId:int, permissionUuid:string, slug:string, type:string}> $overrides
     * @return array<int, array<string, mixed>>
     */
    private function permissionRows(array $slugs, array $permissionMap, array $rolePermissionSlugs, array $overrides): array
    {
        $overrideTypes = [];
        foreach ($overrides as $override) {
            $overrideTypes[(string) $override['slug']] = (string) $override['type'];
        }

        return array_values(array_map(function (string $slug) use ($permissionMap, $rolePermissionSlugs, $overrideTypes): array {
            $permission = $permissionMap[$slug] ?? null;
            return $this->serializePermissionRow(
                $slug,
                $permission,
                isset($overrideTypes[$slug]) && $overrideTypes[$slug] === 'grant' ? 'User grant' : (in_array($slug, $rolePermissionSlugs, true) ? 'Role' : 'Effective'),
            );
        }, $slugs));
    }

    /**
     * @param array<int, array{permissionId:int, permissionUuid:string, slug:string, type:string}> $overrides
     * @param array<string, Permission> $permissionMap
     * @param string[] $rolePermissionSlugs
     * @param string[] $effectiveSlugs
     * @return array<int, array<string, mixed>>
     */
    private function overrideRows(array $overrides, array $permissionMap, array $rolePermissionSlugs, array $effectiveSlugs): array
    {
        return array_values(array_map(function (array $override) use ($permissionMap, $rolePermissionSlugs, $effectiveSlugs): array {
            $slug = (string) $override['slug'];
            return array_merge(
                $this->serializePermissionRow($slug, $permissionMap[$slug] ?? null, 'User ' . (string) $override['type']),
                [
                    'permissionId' => (string) $override['permissionUuid'],
                    'type' => (string) $override['type'],
                    'roleDefault' => in_array($slug, $rolePermissionSlugs, true),
                    'effective' => in_array($slug, $effectiveSlugs, true),
                ],
            );
        }, $overrides));
    }

    private function serializePermissionRow(string $slug, ?Permission $permission, string $source): array
    {
        return [
            'id' => $permission?->uuid,
            'slug' => $slug,
            'name' => $permission?->name ?? $slug,
            'module' => $permission?->module,
            'actionCategory' => $permission?->actionCategory,
            'source' => $source,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentSessionFallbackRow(\WorkEddy\Platform\Session\UserContext $ctx, string $currentSessionId): array
    {
        return [
            'session_id' => $currentSessionId !== '' ? $currentSessionId : null,
            'user_id' => $ctx->userId,
            'role_type' => $ctx->roleType,
            'authz_version' => $ctx->authzVersion,
            'ip_address' => null,
            'user_agent' => null,
            'login_at' => $ctx->loginAt,
            'last_seen_at' => $ctx->loginAt,
        ];
    }

    private function ensureCurrentSessionRow(\WorkEddy\Platform\Session\UserContext $ctx, string $currentSessionId): bool
    {
        if ($currentSessionId === '') {
            return true;
        }

        $row = $this->userSessions->findForSession($currentSessionId, $ctx->userId);
        if ($row !== null) {
            return empty($row['revoked_at']);
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->userSessions->upsert($currentSessionId, $ctx->userId, [
            'role_type' => $ctx->roleType,
            'authz_version' => $ctx->authzVersion,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'login_at' => $ctx->loginAt,
            'last_seen_at' => $now,
        ]);

        return true;
    }

    private function requireUser(string $uuid, ?\WorkEddy\Platform\Session\UserContext $ctx = null): \WorkEddy\Modules\IAM\Domain\User
    {
        $user = $this->users->findByUuid(UuidSupport::requireValid($uuid));
        if ($user === null) {
            throw new NotFoundException('User not found: ' . $uuid);
        }
        if ($ctx !== null && $ctx->organizationUuid !== null && $ctx->organizationUuid !== '') {
            $membership = $this->memberships->findByUserAndOrganizationUuid((int) $user->getId(), $ctx->organizationUuid);
            if ($membership === null) {
                throw new NotFoundException('User not found: ' . $uuid);
            }
        }

        return $user;
    }

    /**
     * @param mixed[] $permissionIds
     * @return int[]
     */
    private function resolvePermissionIds(array $permissionIds): array
    {
        $resolved = [];
        foreach ($permissionIds as $permissionId) {
            $permission = $this->permissionRepository->findByUuid(UuidSupport::requireValid((string) $permissionId, 'permissions'));
            if ($permission === null || $permission->id === null) {
                throw new ValidationException(['permissions' => 'One or more permission IDs are invalid.']);
            }
            $resolved[] = (int) $permission->id;
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(Request $request): array
    {
        return array_replace($request->body, $request->json);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeTenantOptions(int $userId): array
    {
        $rows = [];
        foreach ($this->memberships->findAllByUserId($userId) as $membership) {
            $role = $this->roles->findById($membership->getRoleId());
            $rows[] = [
                'id' => $membership->getOrganizationUuid(),
                'organizationUuid' => $membership->getOrganizationUuid(),
                'organizationName' => $membership->getOrganizationName(),
                'membershipId' => $membership->getUuid(),
                'roleSlug' => $membership->getRoleSlug(),
                'roleName' => $role?->getName() ?? $membership->getRoleSlug(),
                'isPrimary' => $membership->isPrimary(),
            ];
        }

        $user = $this->users->findById($userId);
        $platformRole = $user !== null ? $this->roles->findById($user->getRoleId()) : null;
        if ($user !== null && strtolower($platformRole?->getScope() ?? 'customer') !== 'customer') {
            $rows[] = [
                'id' => 'platform',
                'organizationUuid' => null,
                'organizationName' => 'Platform',
                'membershipId' => null,
                'roleSlug' => $user->getRoleSlug(),
                'roleName' => $this->roles->findById($user->getRoleId())?->getName() ?? $user->getRoleSlug(),
                'isPrimary' => false,
            ];
        }

        return $rows;
    }
}
