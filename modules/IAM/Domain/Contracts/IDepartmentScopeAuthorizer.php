<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain\Contracts;

use WorkEddy\Platform\Session\UserContext;

interface IDepartmentScopeAuthorizer
{
    /**
     * @return list<int>|null Null means unscoped/global access.
     */
    public function accessibleDepartmentIds(UserContext $context, string $privilege): ?array;

    public function requireDepartmentPrivilege(UserContext $context, string $privilege, int $departmentId): void;
}
