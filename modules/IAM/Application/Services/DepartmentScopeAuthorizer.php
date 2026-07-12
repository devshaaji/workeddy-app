<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\Contracts\IDepartmentScopeAuthorizer;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Authorization\PermissionDefinition;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use Doctrine\DBAL\Connection;

final class DepartmentScopeAuthorizer implements IDepartmentScopeAuthorizer
{
    /** @var array<string, list<int>|null> */
    private array $scopeCache = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly IPermissionService $permissions,
    ) {}

    public function accessibleDepartmentIds(UserContext $context, string $privilege): ?array
    {
        $cacheKey = (string) $context->userId . ':' . $context->roleType . ':' . PermissionDefinition::normalizeKey($privilege);
        if (array_key_exists($cacheKey, $this->scopeCache)) {
            return $this->scopeCache[$cacheKey];
        }

        $employee = $this->connection->fetchAssociative(
            'SELECT id, department_id FROM hrm_employees WHERE iam_user_id = ? AND deleted_at IS NULL ORDER BY id ASC LIMIT 1',
            [(int) $context->userId]
        );

        if ($employee === false || !isset($employee['department_id'])) {
            return $this->scopeCache[$cacheKey] = null;
        }

        $scopeMode = $this->connection->fetchOne(
            'SELECT scope_mode FROM iam_role_department_scopes WHERE role_slug = ? AND permission_key = ?',
            [$context->roleType, PermissionDefinition::normalizeKey($privilege)]
        );

        $departmentId = (int) $employee['department_id'];
        $resolvedScope = is_string($scopeMode) && $scopeMode !== '' ? strtolower($scopeMode) : 'self';

        return $this->scopeCache[$cacheKey] = match ($resolvedScope) {
            'global' => null,
            'subtree' => $this->departmentSubtreeIds($departmentId),
            default => [$departmentId],
        };
    }

    public function requireDepartmentPrivilege(UserContext $context, string $privilege, int $departmentId): void
    {
        $this->permissions->requirePrivilege($context, $privilege);

        $accessibleDepartmentIds = $this->accessibleDepartmentIds($context, $privilege);
        if ($accessibleDepartmentIds === null) {
            return;
        }

        if (!in_array($departmentId, $accessibleDepartmentIds, true)) {
            throw new ForbiddenException('Department-scoped authorization denied.');
        }
    }

    /**
     * @return list<int>
     */
    private function departmentSubtreeIds(int $rootDepartmentId): array
    {
        $queue = [$rootDepartmentId];
        $seen = [];

        while ($queue !== []) {
            $departmentId = array_shift($queue);
            if ($departmentId === null || in_array($departmentId, $seen, true)) {
                continue;
            }

            $seen[] = $departmentId;
            $children = $this->connection->fetchFirstColumn(
                'SELECT id FROM hrm_departments WHERE parent_department_id = ? AND is_archived = 0',
                [$departmentId]
            );

            foreach ($children as $childId) {
                $queue[] = (int) $childId;
            }
        }

        sort($seen);

        return $seen;
    }
}
