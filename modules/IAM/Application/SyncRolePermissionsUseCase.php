<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;

use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Support\UuidSupport;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;

final class SyncRolePermissionsUseCase
{
    public function __construct(
        private readonly IRoleRepository $roles,
        private readonly IPermissionRepository $permissionsRepository,
        private readonly IAuditService $audit,
    ) {}

    public function execute(int $roleId, array $permissionIds, UserContext $ctx): \WorkEddy\Modules\IAM\Domain\Role
    {
        $role = $this->roles->findById($roleId);
        if ($role === null) {
            throw new NotFoundException('Role', $roleId);
        }

        $normalizedPermissionIds = $this->normalizePermissionIds($permissionIds);
        $beforePermissions = $role->getPermissions();
        $this->roles->syncPermissions($roleId, $normalizedPermissionIds);
        $updated = $this->roles->findById($roleId);

        $this->audit->record(
            action: 'iam.role.permissions.synced',
            entityType: 'Role',
            entityId: (string) $roleId,
            beforeState: ['permissions' => $beforePermissions],
            afterState: ['permissions' => $updated?->getPermissions() ?? [], 'module' => 'IAM', 'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null],
            actorId: (string) $ctx->userId,
        );

        return $updated ?? $role;
    }

    /**
     * @param mixed[] $permissionIds
     * @return int[]
     */
    public function normalizePermissionIds(array $permissionIds): array
    {
        $normalized = [];
        foreach (array_values(array_unique($permissionIds, SORT_REGULAR)) as $permissionId) {
            $permission = null;
            if (is_string($permissionId) && trim($permissionId) !== '') {
                $permission = $this->permissionsRepository->findByUuid(UuidSupport::requireValid(trim($permissionId), 'permissionIds'));
            } else {
                $permission = $this->permissionsRepository->findById((int) $permissionId);
            }

            if ($permission === null || $permission->id === null || $permission->id <= 0) {
                throw new ValidationException(['permissionIds' => 'One or more permission IDs are invalid.']);
            }

            $normalized[] = (int) $permission->id;
        }

        return array_values(array_unique($normalized));
    }
}
