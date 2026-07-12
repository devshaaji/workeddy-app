<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Presentation;

use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Session\ISessionService;

final class PermissionController
{
    public function __construct(
        private readonly IPermissionRepository $permissionRepository,
        private readonly IPermissionService $permissions,
        private readonly ISessionService $session,
    ) {}

    public function list(Request $request): array
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return ['status' => 'error', 'message' => 'Unauthenticated'];
        }

        $this->permissions->requirePrivilege($ctx, IAMPermissions::PERMISSION_ASSIGN);

        $module = $request->query('module') !== null && trim((string) $request->query('module')) !== ''
            ? trim((string) $request->query('module'))
            : null;

        $permissions = $module === null
            ? $this->permissionRepository->findAll()
            : $this->permissionRepository->findByModule($module);

        return [
            'status' => 'ok',
            'data' => array_map(static fn($permission): array => [
                'id' => $permission->uuid,
                'slug' => $permission->slug,
                'name' => $permission->name,
                'module' => $permission->module,
                'description' => $permission->description,
                'actionCategory' => $permission->actionCategory,
                'riskLevel' => $permission->riskLevel,
                'defaultAssignments' => $permission->defaultAssignments,
                'systemOnly' => $permission->systemOnly,
            ], $permissions),
        ];
    }
}
