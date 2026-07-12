<?php

/**
 * AuthService — concrete implementation of IAuthService.
 *
 * Delegates to LoginUseCase and LogoutUseCase.
 * This is the public contract implementation that other modules can consume.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\Contracts\IAuthService;
use WorkEddy\Modules\IAM\Application\LoginUseCase;
use WorkEddy\Modules\IAM\Application\LogoutUseCase;
use WorkEddy\Modules\IAM\Application\DTOs\LoginRequest;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;

final class AuthService implements IAuthService
{
    public function __construct(
        private readonly LoginUseCase    $loginUseCase,
        private readonly LogoutUseCase   $logoutUseCase,
        private readonly ISessionService $session,
    ) {}

    public function authenticate(string $email, string $password): UserContext
    {
        $result = $this->loginUseCase->execute(new LoginRequest(
            email: $email,
            password: $password,
        ));

        return new UserContext(
            tenantId: $result->tenantId,
            userId: $result->userId,
            roleId: $result->roleId,
            organizationId: $result->organizationId,
            organizationUuid: $result->organizationUuid,
            membershipId: $result->membershipId,
            membershipUuid: $result->membershipUuid,
            platformRoleId: $result->platformRoleId,
            platformRoleType: $result->platformRoleSlug,
            membershipRoleId: $result->membershipRoleId,
            membershipRoleType: $result->membershipRoleSlug,
            roleType: $result->roleSlug,
            privileges: $result->privileges,
            loginAt: $result->loginAt,

            authzVersion: $result->authzVersion,
        );
    }

    public function logout(): void
    {
        $ctx = $this->session->getUserContext();
        $this->logoutUseCase->execute($ctx);
    }
}
