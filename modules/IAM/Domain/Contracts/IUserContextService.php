<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain\Contracts;

use WorkEddy\Platform\Session\UserContext;

/** User context service — retrieves user context for session-based requests. */
interface IUserContextService
{
    /** @throws \WorkEddy\Shared\Exceptions\NotFoundException If user not found. */
    public function getById(int|string $userId): UserContext;
}
