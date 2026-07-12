<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Application\Services\AuthenticationThrottleService;
use WorkEddy\Modules\IAM\Application\Services\IAMAuthNotificationDispatcher;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Infrastructure\OTPRepository;
use WorkEddy\Modules\IAM\Settings\IAMSettings;

use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Shared\Exceptions\GatewayException;
use WorkEddy\Shared\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class RequestPasswordResetUseCase
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly IUserRepository $users,
        private readonly OTPRepository $otps,
        private readonly AuthenticationThrottleService $throttle,
        private readonly IAuditService $audit,
        private readonly IAMSettings $settings,
        private readonly IAMAuthNotificationDispatcher $authNotifications,
        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('security') ?? new NullLogger();
    }

    /** @return array{accepted: bool, userId?: int} */
    public function execute(string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new ValidationException(['identifier' => 'Email is required.']);
        }
        if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['identifier' => 'A valid email address is required.']);
        }
        if (!$this->settings->passwordResetEnabled()) {
            throw new ValidationException(['identifier' => 'Password reset is currently disabled.']);
        }

        $this->throttle->assertPasswordResetAllowed($identifier);

        $user = $this->users->findByEmail($identifier);

        if ($user === null || !$user->isActive()) {
            $this->logger->info('Password reset requested for unknown or inactive account.', [
                'identifierHash' => hash('sha256', strtolower($identifier)),
            ]);

            $this->throttle->markPasswordResetRequested($identifier);
            return ['accepted' => true];
        }

        $code = (string) random_int(100000, 999999);
        $userId = $user->getId() ?? 0;
        $this->otps->invalidateAll($userId, OTPRepository::PURPOSE_PASSWORD_RESET);
        $otpId = $this->otps->create($userId, $code, OTPRepository::PURPOSE_PASSWORD_RESET);

        try {
            $this->authNotifications->sendPasswordReset($user, $code, $otpId, $this->otps->expiryMinutes());
        } catch (GatewayException $e) {
            $this->otps->invalidateAll($userId, OTPRepository::PURPOSE_PASSWORD_RESET);
            $this->throttle->clearPasswordResetCooldown($identifier);
            throw $e;
        }

        $this->throttle->markPasswordResetRequested($identifier);

        $this->audit->record(
            action: 'iam.password_reset.requested',
            entityType: 'User',
            entityId: (string) $userId,
            afterState: ['module' => 'IAM', 'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null],
            actorId: (string) $userId,
        );

        return ['accepted' => true, 'userId' => $userId];
    }
}
