<?php

/**
 * SuspendUserUseCase — admin suspends a user account.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Application\Services\IAMNotificationDispatcher;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class SuspendUserUseCase
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
        $this->permissionService->requirePrivilege($ctx, IAMPermissions::USER_SUSPEND);

        // Cannot suspend yourself
        if ($ctx->userId === $targetUserId) {
            $this->logger->warning('User suspension rejected because actor targeted own account.', [
                'actorId' => $ctx->userId,
                'targetUserId' => $targetUserId,
            ]);

            throw new ForbiddenException('Cannot suspend your own account.');
        }

        $user = $this->userRepo->findById($targetUserId);
        if ($user === null) {
            throw new NotFoundException('User', $targetUserId);
        }

        $beforeStatus = $user->getStatus()->value;

        $this->tx->transactional(function () use ($user): void {
            // Domain enforces: ConflictException if already suspended
            $user->suspend();
            $this->userRepo->update($user);
        });

        $this->auditService->record(
            action: 'iam.user.suspended',
            entityType: 'User',
            entityId: (string) $targetUserId,
            beforeState: ['status' => $beforeStatus],
            afterState: ['status' => $user->getStatus()->value],
            actorId: (string) $ctx->userId,
            actorType: 'User'
        );

        $this->logger->warning('User account suspended.', [
            'actorId' => $ctx->userId,
            'targetUserId' => $targetUserId,
            'beforeStatus' => $beforeStatus,
            'afterStatus' => $user->getStatus()->value,
        ]);

        $this->notifications->userEvent(
            event: 'user_suspended',
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
