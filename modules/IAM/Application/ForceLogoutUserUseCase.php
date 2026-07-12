<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Application\Services\IAMNotificationDispatcher;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\IUserSessionRepository;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class ForceLogoutUserUseCase
{
    public function __construct(
        private readonly IUserRepository $userRepo,
        private readonly IPermissionService $permissionService,
        private readonly IUserSessionRepository $userSessions,
        private readonly IAuditService $auditService,
        private readonly IAMNotificationDispatcher $notifications,
    ) {}

    public function execute(int $targetUserId, UserContext $ctx): int
    {
        $this->permissionService->requirePrivilege($ctx, IAMPermissions::USER_PASSWORD_RESET);

        if ($ctx->userId === $targetUserId) {
            throw new ForbiddenException('Cannot force logout your own account.');
        }

        $user = $this->userRepo->findById($targetUserId);
        if ($user === null) {
            throw new NotFoundException('User', $targetUserId);
        }

        $revoked = 0;
        foreach ($this->userSessions->listActiveForUser($targetUserId) as $row) {
            $sessionId = isset($row['session_id']) ? (string) $row['session_id'] : '';
            if ($sessionId === '') {
                continue;
            }

            $this->userSessions->revoke($sessionId, $ctx->userId);
            $revoked++;
        }

        $this->auditService->record(
            action: 'iam.user.force_logged_out',
            entityType: 'User',
            entityId: (string) $targetUserId,
            beforeState: null,
            afterState: ['revokedSessionCount' => $revoked],
            actorId: (string) $ctx->userId,
            actorType: 'User'
        );

        if ($revoked > 0) {
            $this->notifications->userEvent(
                event: 'force_logout',
                recipientUserId: $targetUserId,
                sourceUserId: $targetUserId,
                sourceUserUuid: $user->getUuid(),
                actorUserId: $ctx->userId,
                context: [
                    'name' => $user->getFullName(),
                    'email' => $user->getEmail(),
                    'revokedSessionCount' => $revoked,
                ],
            );
        }

        return $revoked;
    }
}
