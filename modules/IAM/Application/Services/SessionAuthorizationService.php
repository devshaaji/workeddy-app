<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Authorization\IAuthorizationService;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Shared\Exceptions\AuthenticationException;

final class SessionAuthorizationService implements IAuthorizationService
{
    public function __construct(
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
    ) {}

    public function authorize(string $permission, ?string $tenantId = null): void
    {
        unset($tenantId);

        $context = $this->session->getUserContext();
        if ($context === null) {
            throw new AuthenticationException('Authentication required');
        }

        $this->permissions->requirePrivilege($context, $permission);
    }
}
