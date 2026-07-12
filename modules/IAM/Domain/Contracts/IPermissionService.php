<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain\Contracts;

use WorkEddy\Platform\Session\UserContext;

/** Permission service contract. */
interface IPermissionService
{
    /**
     * Assert that the user has a specific privilege.
     *
     * @throws \WorkEddy\Shared\Exceptions\ForbiddenException If privilege not held.
     */
    public function requirePrivilege(UserContext $ctx, string $privilege): void;
}
