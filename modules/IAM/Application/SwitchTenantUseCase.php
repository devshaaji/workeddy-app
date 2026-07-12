<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Application\Services\UserContextFactory;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;

final class SwitchTenantUseCase
{
    public function __construct(
        private readonly IUserRepository $users,
        private readonly IOrganizationMembershipRepository $memberships,
        private readonly IRoleRepository $roles,
        private readonly UserContextFactory $contextFactory,
        private readonly ISessionService $session,
    ) {
    }

    public function execute(string $tenantId, UserContext $actor): UserContext
    {
        $user = $this->users->findById($actor->userId);
        if ($user === null) {
            throw new NotFoundException('User', (int) $actor->userId);
        }
        if (!$user->isActive()) {
            throw new ForbiddenException('Inactive users cannot switch organization context.');
        }

        $tenantId = trim($tenantId);
        if ($tenantId === '') {
            throw new ValidationException(['tenantId' => 'Tenant selection is required.']);
        }

        if ($tenantId === 'platform') {
            $platformRole = $this->roles->findById($user->getRoleId());
            if ($platformRole === null || strtolower($platformRole->getScope()) === 'customer') {
                throw new ForbiddenException('Platform access is not available for this account.');
            }
            $context = $this->contextFactory->fromPlatformRole($user, $actor->loginAt);
        } else {
            $context = $this->buildMembershipContext($tenantId, $user, $actor);
        }

        $this->session->setUserContext($context);

        return $context;
    }

    private function buildMembershipContext(string $organizationUuid, \WorkEddy\Modules\IAM\Domain\User $user, UserContext $actor): UserContext
    {
        $membership = $this->memberships->findByUserAndOrganizationUuid($user->getId(), $organizationUuid);
        if ($membership === null) {
            throw new NotFoundException('Organization membership');
        }

        return $this->contextFactory->fromMembership($user, $membership, $actor->loginAt);
    }
}
