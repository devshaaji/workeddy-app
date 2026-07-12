<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Application\Services\AuthenticationThrottleService;
use WorkEddy\Modules\IAM\Application\Services\IAMAuthNotificationDispatcher;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Infrastructure\OTPRepository;

use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Shared\Exceptions\GatewayException;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ResendOTPUseCase
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly IUserRepository $users,
        private readonly OTPRepository $otps,
        private readonly AuthenticationThrottleService $throttle,
        private readonly IAuditService $audit,
        private readonly ISessionService $session,
        private readonly IAMAuthNotificationDispatcher $authNotifications,
        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('security') ?? new NullLogger();
    }

    /** @return array{userId: int, userUuid: string} */
    public function execute(int $userId, ?string $ipAddress = null): array
    {
        if ($userId <= 0) {
            throw new ValidationException(['userId' => 'User id is required.']);
        }

        $this->assertPendingAuthentication($userId, $ipAddress);
        $this->throttle->assertOtpResendAllowed($userId);

        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new NotFoundException('User', $userId);
        }
        if (!$user->isActive()) {
            throw new ValidationException(['userId' => 'OTP cannot be issued for this account status.']);
        }

        $code = (string) random_int(100000, 999999);
        $this->otps->invalidateAll($userId, OTPRepository::PURPOSE_LOGIN);
        $otpId = (int) $this->otps->create($userId, $code, OTPRepository::PURPOSE_LOGIN);

        try {
            $this->authNotifications->sendLoginOtp($user, $code, $otpId, $this->otps->expiryMinutes());
        } catch (GatewayException $e) {
            $this->otps->invalidateAll($userId, OTPRepository::PURPOSE_LOGIN);
            $this->throttle->clearOtpResendCooldown($userId);
            throw $e;
        }

        $this->refreshPendingAuthenticationWindow($userId);
        $this->throttle->markOtpSent($userId);

        $this->audit->record(
            action: 'iam.otp.resent',
            entityType: 'User',
            entityId: (string) $userId,
            afterState: ['module' => 'IAM', 'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null],
            actorId: (string) $userId,
        );

        $this->logger->info('OTP resent.', ['targetUserId' => $userId]);

        $result = ['userId' => $userId, 'userUuid' => $user->getUuid()];
        return $result;
    }

    private function assertPendingAuthentication(int $userId, ?string $ipAddress): void
    {
        $pending = $this->session->get('pending_auth');
        if (!is_array($pending) || (int) ($pending['userId'] ?? 0) !== $userId) {
            throw new AuthenticationException('OTP resend requires an active login challenge.');
        }

        if (trim((string) ($pending['challengeId'] ?? '')) === '') {
            $this->session->set('pending_auth', null);
            throw new AuthenticationException('OTP resend requires an active login challenge.');
        }

        $challengeIp = (string) ($pending['ipAddress'] ?? '');
        if ($challengeIp !== '' && $ipAddress !== null && $ipAddress !== '' && !hash_equals($challengeIp, $ipAddress)) {
            $this->session->set('pending_auth', null);
            throw new AuthenticationException('OTP resend session changed. Please log in again.');
        }

        $expiresAt = isset($pending['expiresAt']) ? strtotime((string) $pending['expiresAt']) : false;
        if ($expiresAt === false || $expiresAt < time()) {
            $this->session->set('pending_auth', null);
            throw new AuthenticationException('OTP resend window has expired. Please log in again.');
        }
    }

    private function refreshPendingAuthenticationWindow(int $userId): void
    {
        $pending = $this->session->get('pending_auth');
        if (!is_array($pending) || (int) ($pending['userId'] ?? 0) !== $userId) {
            return;
        }

        $now = new \DateTimeImmutable();
        $pending['issuedAt'] = $now->format('c');
        $pending['expiresAt'] = $now->modify('+' . $this->otps->expiryMinutes() . ' minutes')->format('c');

        $this->session->set('pending_auth', $pending);
    }
}
