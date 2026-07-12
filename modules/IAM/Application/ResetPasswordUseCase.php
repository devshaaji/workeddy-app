<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Application\Services\IAMAuthNotificationDispatcher;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Infrastructure\OTPRepository;
use WorkEddy\Modules\IAM\Settings\IAMSettings;

use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Shared\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ResetPasswordUseCase
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly IUserRepository $users,
        private readonly OTPRepository $otps,
        private readonly IAuditService $audit,
        private readonly IAMSettings $settings,
        private readonly IAMAuthNotificationDispatcher $authNotifications,
        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('security') ?? new NullLogger();
    }

    public function execute(int $userId, string $code, string $newPassword): void
    {
        if (!$this->settings->passwordResetEnabled()) {
            throw new ValidationException(['code' => 'Password reset is currently disabled.']);
        }

        $errors = [];
        if ($userId <= 0) {
            $errors['userId'] = 'User id is required.';
        }
        if (strlen(trim($code)) < 4) {
            $errors['code'] = 'Reset code is required.';
        }
        if (strlen($newPassword) < $this->settings->minPasswordLength()) {
            $errors['newPassword'] = 'Password is too short.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            // Return the same error as invalid code to prevent user enumeration
            $this->logger->warning('Password reset rejected because user does not exist.', [
                'targetUserId' => $userId,
            ]);

            throw new ValidationException(['code' => 'Invalid or expired reset code.']);
        }

        $otp = $this->otps->verifyLatestValid($userId, OTPRepository::PURPOSE_PASSWORD_RESET, $code);
        if ($otp === null) {
            $this->logger->warning('Password reset rejected because code verification failed.', [
                'targetUserId' => $userId,
                'reason' => 'missing_expired_or_invalid',
            ]);

            throw new ValidationException(['code' => 'Invalid or expired reset code.']);
        }

        $newHash = password_hash(
            $newPassword,
            $this->settings->passwordAlgorithmConstant(),
            $this->settings->passwordHashOptions(),
        );

        $user->changePassword($newHash);
        $this->users->update($user);
        $this->otps->markUsed((int) $otp['id']);
        $this->otps->invalidateAll($userId, OTPRepository::PURPOSE_PASSWORD_RESET);

        $this->audit->record(
            action: 'iam.password_reset.completed',
            entityType: 'User',
            entityId: (string) $userId,
            afterState: ['module' => 'IAM', 'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null],
            actorId: (string) $userId,
        );

        $this->logger->info('Password reset completed.', ['targetUserId' => $userId]);
        $this->authNotifications->sendPasswordResetCompleted($user);
    }
}
