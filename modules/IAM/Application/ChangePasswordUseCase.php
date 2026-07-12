<?php

/**
 * ChangePasswordUseCase — self-service password change.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Application\DTOs\ChangePasswordRequest;
use WorkEddy\Modules\IAM\Application\Services\IAMNotificationDispatcher;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Settings\IAMSettings;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Audit\IAuditService;

use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ChangePasswordUseCase
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly IUserRepository $userRepo,
        private readonly IPermissionService $permissionService,
        private readonly IAuditService   $audit,
        private readonly IAMSettings     $iamSettings,
        private readonly IAMNotificationDispatcher $notifications,
        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('security') ?? new NullLogger();
    }

    public function execute(ChangePasswordRequest $request, UserContext $ctx): void
    {
        if ($ctx->userId === $request->userId) {
            $this->permissionService->requirePrivilege($ctx, IAMPermissions::USER_PASSWORD_CHANGE);
        } else {
            $this->permissionService->requirePrivilege($ctx, IAMPermissions::USER_PASSWORD_RESET);
        }

        // Validate new password strength (configurable policy)
        $minLen = $this->iamSettings->minPasswordLength();
        if (strlen($request->newPassword) < $minLen) {
            throw new ValidationException(['newPassword' => "Password must be at least {$minLen} characters."]);
        }

        // Load user
        $user = $this->userRepo->findById($request->userId);
        if ($user === null) {
            throw new NotFoundException('User', $request->userId);
        }

        $isSelfService = $ctx->userId === $request->userId;

        // Self-service password changes must prove knowledge of the current
        // password. Admin resets are authorized by iam.user.password.reset.
        if ($isSelfService && !$user->verifyPassword($request->currentPassword)) {
            $this->logger->warning('Password change rejected because current password verification failed.', [
                'actorId' => $ctx->userId,
                'targetUserId' => $request->userId,
            ]);

            throw new ValidationException(['currentPassword' => 'Current password is incorrect.']);
        }

        // Hash new password with configurable algorithm + parameters
        $newHash = password_hash(
            $request->newPassword,
            $this->iamSettings->passwordAlgorithmConstant(),
            $this->iamSettings->passwordHashOptions(),
        );

        // Apply change
        $user->changePassword($newHash);
        $this->userRepo->update($user);

        // Audit (never log password values)
        $this->audit->record(
            action: 'iam.password.changed',
            entityType: 'User',
            entityId: (string) $user->getId(),
            afterState: ['module' => 'IAM'],
            actorId: (string) $ctx->userId,
        );

        $this->logger->info('User password changed.', [
            'actorId' => $ctx->userId,
            'targetUserId' => $user->getId(),
            'selfService' => $isSelfService,
        ]);

        $this->notifications->userEvent(
            event: 'password_changed',
            recipientUserId: (int) $user->getId(),
            sourceUserId: (int) $user->getId(),
            sourceUserUuid: $user->getUuid(),
            actorUserId: $ctx->userId,
            context: [
                'name' => $user->getFullName(),
                'email' => $user->getEmail(),
                'selfService' => $isSelfService,
            ],
        );
    }
}
