<?php

/**
 * AssignRoleUseCase — admin assigns a role to a user.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Application\DTOs\AssignRoleRequest;
use WorkEddy\Modules\IAM\Application\Services\IAMNotificationDispatcher;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class AssignRoleUseCase
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly IUserRepository $userRepo,
        private readonly IRoleRepository $roleRepo,
        private readonly IOrganizationMembershipRepository $membershipRepo,
        private readonly IPermissionService $permissionService,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $auditService,
        private readonly IAMNotificationDispatcher $notifications,
        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('security') ?? new NullLogger();
    }

    public function execute(AssignRoleRequest $request, UserContext $ctx): void
    {
        $this->permissionService->requirePrivilege($ctx, IAMPermissions::ROLE_ASSIGN);

        $user = $this->userRepo->findById($request->userId);
        if ($user === null) {
            throw new NotFoundException('User', $request->userId);
        }

        $role = $this->roleRepo->findBySlug($request->roleSlug);
        if ($role === null) {
            throw new NotFoundException('Role');
        }
        $beforeRole = $user->getEffectiveRoleSlug();

        $this->tx->transactional(function () use ($user, $role): void {
            $membership = $this->membershipRepo->findPrimaryByUserId((int) $user->getId());
            if ($membership !== null) {
                $membership->assignRole((int) $role->getId(), $role->getSlug());
                $this->membershipRepo->update($membership);
            } else {
                $user->assignRole((int) $role->getId(), $role->getSlug());
            }

            $user->bumpAuthzVersion();
            $this->userRepo->update($user);
        });

        $this->auditService->record(
            action: 'iam.role.assigned',
            entityType: 'User',
            entityId: (string) $user->getId(),
            beforeState: ['roleSlug' => $beforeRole],
            afterState: ['roleSlug' => $role->getSlug()],
            actorId: (string) $ctx->userId,
            actorType: 'User'
        );

        $this->logger->info('User role assigned.', [
            'actorId' => $ctx->userId,
            'targetUserId' => $user->getId(),
            'beforeRoleSlug' => $beforeRole,
            'afterRoleSlug' => $role->getSlug(),
        ]);

        $this->notifications->userEvent(
            event: 'role_assigned',
            recipientUserId: (int) $user->getId(),
            sourceUserId: (int) $user->getId(),
            sourceUserUuid: $user->getUuid(),
            actorUserId: $ctx->userId,
            context: [
                'name' => $user->getFullName(),
                'email' => $user->getEmail(),
                'previousRole' => $beforeRole,
                'role' => $role->getName(),
                'roleSlug' => $role->getSlug(),
            ],
        );
    }
}
