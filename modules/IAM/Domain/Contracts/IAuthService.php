<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain\Contracts;

use WorkEddy\Platform\Session\UserContext;

/** Authentication service contract. */
interface IAuthService
{
    /**
     * Authenticate user by credentials.
     *
     * @throws \WorkEddy\Shared\Exceptions\ValidationException If credentials invalid.
     * @throws \WorkEddy\Shared\Exceptions\ForbiddenException If account suspended.
     */
    public function authenticate(string $email, string $password): UserContext;

    /** Logout: destroy session. */
    public function logout(): void;
}
