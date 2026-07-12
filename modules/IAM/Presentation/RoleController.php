<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Presentation;

use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Application\SyncRolePermissionsUseCase;
use WorkEddy\Modules\IAM\Application\UpsertRoleUseCase;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\Permission;
use WorkEddy\Modules\IAM\Domain\Role;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Shared\Support\UuidSupport;

final class RoleController
{
    public function __construct(
        private readonly IRoleRepository $roles,
        private readonly IUserRepository $users,
        private readonly IPermissionRepository $permissionRepository,
        private readonly UpsertRoleUseCase $upsertRole,
        private readonly SyncRolePermissionsUseCase $syncRolePermissions,
        private readonly IPermissionService $permissions,
        private readonly ISessionService $session,
    ) {}

    public function list(Request $request): Response
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }

        $this->requireAnyPrivilege($ctx, [
            IAMPermissions::ROLE_MANAGE,
            IAMPermissions::ROLE_ASSIGN,
            IAMPermissions::USER_CREATE,
        ]);

        $roles = $this->roles->findAll();
        $userCounts = $this->roleUserCounts($roles);
        $permissionMap = $this->permissionMap();

        return Response::json([
            'status' => 'ok',
            'data' => array_map(
                fn(Role $role): array => $this->serializeListRole($role, $userCounts, $permissionMap, $ctx),
                $roles,
            ),
        ]);
    }

    public function show(Request $request): Response
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }

        $this->permissions->requirePrivilege($ctx, IAMPermissions::ROLE_MANAGE);

        $role = $this->requireRole((string) ($request->routeParam('id', '')));

        return Response::json(['status' => 'ok', 'data' => $this->serialize(
            $role,
            $this->roleUserCounts([$role]),
            $this->permissionMap(),
            includePermissions: true,
            includeUsers: true,
        )]);
    }

    public function assignPermissions(Request $request): Response
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }

        $this->permissions->requirePrivilege($ctx, IAMPermissions::PERMISSION_ASSIGN);

        $body = $this->requestData($request);
        $permissionIds = $body['permissionIds'] ?? $body['permission_ids'] ?? [];
        if (!is_array($permissionIds)) {
            throw new \WorkEddy\Shared\Exceptions\ValidationException(['permissionIds' => 'Permission IDs must be an array.']);
        }

        $role = $this->syncRolePermissions->execute((int) $this->requireRole((string) ($request->routeParam('id', '')))->getId(), $this->normalizePermissionIds($permissionIds), $ctx);

        return Response::json([
            'status' => 'ok',
            'data' => $this->serialize(
                $role,
                $this->roleUserCounts([$role]),
                $this->permissionMap(),
                includePermissions: true,
            ),
        ]);
    }

    public function pendingMutation(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return Response::error('Unauthenticated', 401);
        }

        $this->permissions->requirePrivilege($ctx, IAMPermissions::ROLE_MANAGE);
        $body = $this->normalizeMutationBody($this->requestData($request));
        $role = $this->upsertRole->execute(isset($vars['id']) ? (int) $this->requireRole((string) $vars['id'])->getId() : null, $body, $ctx);

        return Response::json([
            'status' => 'ok',
            'data' => $this->serialize(
                $role,
                $this->roleUserCounts([$role]),
                $this->permissionMap(),
                includePermissions: true,
            ),
        ], isset($vars['id']) ? 200 : 201);
    }

    /**
     * @param array<int, int> $userCounts
     * @param array<string, Permission> $permissionMap
     */
    private function serializeListRole(Role $role, array $userCounts, array $permissionMap, \WorkEddy\Platform\Session\UserContext $ctx): array
    {
        $roleId = (int) $role->getId();

        return [
            'id' => $role->getUuid(),
            'slug' => $role->getSlug(),
            'name' => $role->getName(),
            'description' => $role->getDescription(),
            'isSystem' => $role->isSystem(),
            'scope' => $role->getScope(),
            'permissionCount' => count($role->getPermissions()),
            'riskLevel' => $this->deriveRiskLevel($role->getPermissions(), $permissionMap),
            'userCount' => $userCounts[$roleId] ?? 0,
            'actions' => $this->roleActions($ctx, $role->getUuid()),
        ];
    }

    /**
     * @param array<int, int> $userCounts
     * @param array<string, Permission> $permissionMap
     */
    private function serialize(
        Role $role,
        array $userCounts = [],
        array $permissionMap = [],
        bool $includePermissions = false,
        bool $includeUsers = false,
    ): array {
        $roleId = (int) $role->getId();
        $data = [
            'id' => $role->getUuid(),
            'slug' => $role->getSlug(),
            'name' => $role->getName(),
            'description' => $role->getDescription(),
            'isSystem' => $role->isSystem(),
            'scope' => $role->getScope(),
            'permissionCount' => count($role->getPermissions()),
            'riskLevel' => $this->deriveRiskLevel($role->getPermissions(), $permissionMap),
            'userCount' => $userCounts[$roleId] ?? 0,
        ];

        if ($includePermissions) {
            $data['permissions'] = $role->getPermissions();
        }

        if ($includeUsers) {
            $data['assignedUsers'] = array_map(
                fn(\WorkEddy\Modules\IAM\Domain\User $user): array => $this->serializeAssignedUser($user),
                $this->users->findByRoleId($roleId),
            );
        }

        return $data;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function roleActions(\WorkEddy\Platform\Session\UserContext $ctx, string $roleUuid): array
    {
        if (!in_array(IAMPermissions::ROLE_MANAGE, $ctx->privileges, true)) {
            return [];
        }

        return [
            ['key' => 'edit', 'label' => 'Edit', 'method' => 'LINK', 'url' => '/roles/' . $roleUuid . '/edit', 'variant' => 'secondary'],
            ['key' => 'permissions', 'label' => 'Permissions', 'method' => 'LINK', 'url' => '/roles/' . $roleUuid . '/permissions', 'variant' => 'secondary'],
        ];
    }

    /**
     * @param Role[] $roles
     * @return array<int, int>
     */
    private function roleUserCounts(array $roles): array
    {
        return $this->users->countByRoleIds(array_map(
            static fn(Role $role): int => (int) $role->getId(),
            $roles,
        ));
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
     * @param string[] $permissionSlugs
     * @param array<string, Permission> $permissionMap
     */
    private function deriveRiskLevel(array $permissionSlugs, array $permissionMap): ?string
    {
        $rank = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $highest = null;
        $highestRank = 0;

        foreach ($permissionSlugs as $slug) {
            $risk = strtolower((string) ($permissionMap[$slug]->riskLevel ?? ''));
            if (($rank[$risk] ?? 0) > $highestRank) {
                $highest = $risk;
                $highestRank = $rank[$risk];
            }
        }

        return $highest;
    }

    private function requireRole(string $uuid): Role
    {
        $role = $this->roles->findByUuid(UuidSupport::requireValid($uuid));
        if ($role === null) {
            throw new \WorkEddy\Shared\Exceptions\NotFoundException('Role ' . $uuid . ' not found');
        }

        return $role;
    }

    /**
     * @param list<string> $privileges
     */
    private function requireAnyPrivilege(\WorkEddy\Platform\Session\UserContext $ctx, array $privileges): void
    {
        $lastException = null;
        foreach ($privileges as $privilege) {
            try {
                $this->permissions->requirePrivilege($ctx, $privilege);
                return;
            } catch (\WorkEddy\Shared\Exceptions\ForbiddenException $exception) {
                $lastException = $exception;
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        $this->permissions->requirePrivilege($ctx, $privileges[0] ?? '');
    }

    /**
     * @param mixed[] $permissionIds
     * @return string[]
     */
    private function normalizePermissionIds(array $permissionIds): array
    {
        return array_values(array_map(
            static fn(mixed $permissionId): string => UuidSupport::requireValid((string) $permissionId, 'permissionIds'),
            $permissionIds,
        ));
    }

    private function serializeAssignedUser(\WorkEddy\Modules\IAM\Domain\User $user): array
    {
        return [
            'id' => $user->getUuid(),
            'displayName' => $user->getFullName() !== '' ? $user->getFullName() : $user->getEmail(),
            'status' => $user->getStatus()->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(Request $request): array
    {
        return array_replace($request->body, $request->json);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function normalizeMutationBody(array $body): array
    {
        if (!array_key_exists('name', $body) && array_key_exists('role_name', $body)) {
            $body['name'] = $body['role_name'];
        }
        if (!array_key_exists('slug', $body) && array_key_exists('role_slug', $body)) {
            $body['slug'] = $body['role_slug'];
        }
        if (!array_key_exists('description', $body) && array_key_exists('role_description', $body)) {
            $body['description'] = $body['role_description'];
        }
        if (!array_key_exists('scope', $body) && array_key_exists('role_scope', $body)) {
            $body['scope'] = $body['role_scope'];
        }
        if (!array_key_exists('permissionIds', $body) && array_key_exists('permission_ids', $body)) {
            $body['permissionIds'] = $body['permission_ids'];
        }

        return $body;
    }
}
