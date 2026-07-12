<?php

/**
 * ActivateUserUseCase — admin activates/reactivates a user account.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Application\Services\IAMNotificationDispatcher;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ActivateUserUseCase
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly IUserRepository $userRepo,
        private readonly IPermissionService $permissionService,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $auditService,
        private readonly IAMNotificationDispatcher $notifications,
        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('security') ?? new NullLogger();
    }

    public function execute(int $targetUserId, UserContext $ctx): void
    {
        $this->permissionService->requirePrivilege($ctx, IAMPermissions::USER_ACTIVATE);

        $user = $this->userRepo->findById($targetUserId);
        if ($user === null) {
            throw new NotFoundException('User', $targetUserId);
        }

        $beforeStatus = $user->getStatus()->value;

        $this->tx->transactional(function () use ($user): void {
            $user->activate();
            $this->userRepo->update($user);
        });

        $this->auditService->record(
            action: 'iam.user.activated',
            entityType: 'User',
            entityId: (string) $targetUserId,
            beforeState: ['status' => $beforeStatus],
            afterState: ['status' => $user->getStatus()->value],
            actorId: (string) $ctx->userId,
            actorType: 'User'
        );

        $this->logger->info('User account activated.', [
            'actorId' => $ctx->userId,
            'targetUserId' => $targetUserId,
            'beforeStatus' => $beforeStatus,
            'afterStatus' => $user->getStatus()->value,
        ]);

        $this->notifications->userEvent(
            event: 'user_activated',
            recipientUserId: $targetUserId,
            sourceUserId: $targetUserId,
            sourceUserUuid: $user->getUuid(),
            actorUserId: $ctx->userId,
            context: [
                'name' => $user->getFullName(),
                'email' => $user->getEmail(),
                'status' => $user->getStatus()->value,
            ],
        );
    }
}
