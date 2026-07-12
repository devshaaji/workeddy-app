<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Modules\IAM\Infrastructure\OTPRepository;
use WorkEddy\Platform\Audit\IAuditService;

final class PasswordResetUserInvitationSender implements UserInvitationSenderInterface
{
    public function __construct(
        private readonly OTPRepository $otps,
        private readonly IAMAuthNotificationDispatcher $notifications,
        private readonly IAuditService $audit,
    ) {}

    public function sendPasswordSetup(User $user, ?string $actorId = null): void
    {
        $userId = (int) $user->getId();
        $code = (string) random_int(100000, 999999);

        $this->otps->invalidateAll($userId, OTPRepository::PURPOSE_PASSWORD_RESET);
        $otpId = $this->otps->create($userId, $code, OTPRepository::PURPOSE_PASSWORD_RESET);
        $this->notifications->sendPasswordReset($user, $code, $otpId, $this->otps->expiryMinutes());

        $this->audit->record(
            action: 'iam.password_setup.requested',
            entityType: 'User',
            entityId: (string) $userId,
            afterState: ['module' => 'IAM', 'delivery' => 'email'],
            actorId: $actorId,
            actorType: $actorId !== null ? 'user' : 'system',
        );
    }
}
