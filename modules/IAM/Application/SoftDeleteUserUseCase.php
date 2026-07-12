<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class SoftDeleteUserUseCase
{
    public function __construct(
        private readonly IUserRepository $userRepo,
        private readonly IPermissionService $permissionService,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $auditService,
    ) {}

    public function execute(int $targetUserId, UserContext $ctx): void
    {
        $this->permissionService->requirePrivilege($ctx, IAMPermissions::USER_SUSPEND);

        if ($ctx->userId === $targetUserId) {
            throw new ForbiddenException('Cannot delete your own account.');
        }

        $user = $this->userRepo->findById($targetUserId);
        if ($user === null) {
            throw new NotFoundException('User', $targetUserId);
        }

        $beforeStatus = $user->getStatus()->value;

        $this->tx->transactional(function () use ($user): void {
            $user->softDelete();
            $this->userRepo->update($user);
        });

        $this->auditService->record(
            action: 'iam.user.deleted',
            entityType: 'User',
            entityId: (string) $targetUserId,
            beforeState: ['status' => $beforeStatus],
            afterState: ['status' => $user->getStatus()->value],
            actorId: (string) $ctx->userId,
            actorType: 'User'
        );
    }
}
