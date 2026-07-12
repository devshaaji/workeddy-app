<?php

/**
 * VerifyOTPUseCase — verify one-time password for two-factor authentication.
 *
 * Called after LoginUseCase when MFA is enabled for the user's role.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Application\DTOs\VerifyOTPRequest;
use WorkEddy\Modules\IAM\Application\DTOs\LoginResult;
use WorkEddy\Modules\IAM\Application\Services\AuthenticationThrottleService;
use WorkEddy\Modules\IAM\Application\Services\UserContextFactory;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Modules\IAM\Infrastructure\OTPRepository;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Audit\IAuditService;

use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Clock\SystemClock;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class VerifyOTPUseCase
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly IUserRepository       $userRepo,
        private readonly IPermissionRepository  $permissionRepo,
        private readonly OTPRepository          $otpRepo,
        private readonly AuthenticationThrottleService $throttle,
        private readonly ISessionService        $session,
        private readonly IAuditService          $audit,
        private readonly UserContextFactory $contextFactory,
        private readonly ?IClock                $clock = null,
        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('security') ?? new NullLogger();
    }

    /**
     * @throws ValidationException If OTP is invalid or expired.
     * @throws NotFoundException If user not found.
     */
    public function execute(VerifyOTPRequest $request): LoginResult
    {
        // Validate
        if (strlen(trim($request->code)) < 4) {
            throw new ValidationException(['code' => 'OTP code is required.']);
        }

        // Throttle OTP verification attempts to prevent brute-force
        $this->throttle->assertOtpVerificationAllowed($request->userId);

        $this->assertPendingAuthentication($request->userId, $request->ipAddress);

        // Load user
        $user = $this->userRepo->findById($request->userId);
        if ($user === null) {
            throw new NotFoundException('User', $request->userId);
        }

        // Verify OTP
        $otp = $this->otpRepo->verifyLatestValid($request->userId, OTPRepository::PURPOSE_LOGIN, $request->code);
        if ($otp === null) {
            $this->throttle->recordFailedOtpVerification($request->userId);

            $this->logger->warning('OTP verification failed.', [
                'userId' => $request->userId,
                'reason' => 'missing_expired_or_invalid',
            ]);

            $this->audit->record(
                action: 'iam.otp.failed',
                entityType: 'User',
                entityId: (string) $request->userId,
                afterState: ['module' => 'IAM'],
                actorId: (string) $request->userId,
            );
            throw new ValidationException(['code' => 'Invalid or expired OTP code.']);
        }

        // Clear OTP verification throttle on success
        $this->throttle->clearOtpVerificationAttempts($request->userId);

        // Mark OTP as used
        $this->otpRepo->markUsed($otp['id']);
        $this->otpRepo->invalidateAll($request->userId, OTPRepository::PURPOSE_LOGIN);

        // Resolve permissions and establish full session
        $user->recordLogin();
        $this->userRepo->update($user);

        $loginAt = $this->clock()->now()->format('c');
        $ctx = $this->contextFactory->fromUser($user, $loginAt);

        $this->session->regenerate();
        $this->session->set('pending_auth', null);
        $this->session->setUserContext($ctx);
        $this->throttle->clearFailedLogins($user->getEmail());

        $this->audit->record(
            action: 'iam.otp.verified',
            entityType: 'User',
            entityId: (string) $user->getId(),
            afterState: ['module' => 'IAM'],
            actorId: (string) $user->getId(),
        );
        $this->audit->record(
            action: 'iam.login.success',
            entityType: 'User',
            entityId: (string) $user->getId(),
            afterState: ['module' => 'IAM'],
            actorId: (string) $user->getId(),
        );

        $this->logger->info('OTP verified and session established.', [
            'userId' => $user->getId(),
            'roleSlug' => $user->getRoleSlug(),
        ]);

        return new LoginResult(
            userId: $user->getId(),
            userUuid: $user->getUuid(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            roleId: $ctx->roleId,
            roleSlug: $ctx->roleType,
            privileges: $ctx->privileges,
            loginAt: $loginAt,
            tenantId: $user->getOrganizationUuid() ?? 'platform',
            organizationId: $user->getOrganizationId(),
            organizationUuid: $user->getOrganizationUuid(),
            membershipId: $user->getMembershipId(),
            membershipUuid: $user->getMembershipUuid(),
            platformRoleId: $ctx->platformRoleId,
            platformRoleSlug: $ctx->platformRoleType,
            membershipRoleId: $ctx->membershipRoleId,
            membershipRoleSlug: $ctx->membershipRoleType,
            authzVersion: $user->getAuthzVersion(),
            authenticated: true,
            requiresOtp: false,
        );
    }

    private function assertPendingAuthentication(int $userId, ?string $ipAddress): void
    {
        $pending = $this->session->get('pending_auth');
        if (!is_array($pending) || (int) ($pending['userId'] ?? 0) !== $userId) {
            throw new ValidationException(['code' => 'OTP verification session is missing or expired. Please log in again.']);
        }

        if (trim((string) ($pending['challengeId'] ?? '')) === '') {
            $this->session->set('pending_auth', null);
            throw new ValidationException(['code' => 'OTP verification session is missing or expired. Please log in again.']);
        }

        $challengeIp = (string) ($pending['ipAddress'] ?? '');
        if ($challengeIp !== '' && $ipAddress !== null && $ipAddress !== '' && !hash_equals($challengeIp, $ipAddress)) {
            $this->session->set('pending_auth', null);
            throw new ValidationException(['code' => 'OTP verification session changed. Please log in again.']);
        }

        $expiresAt = isset($pending['expiresAt']) ? strtotime((string) $pending['expiresAt']) : false;
        if ($expiresAt === false || $expiresAt < $this->clock()->now()->getTimestamp()) {
            $this->session->set('pending_auth', null);
            throw new ValidationException(['code' => 'OTP verification session has expired. Please log in again.']);
        }
    }

    private function clock(): IClock
    {
        return $this->clock ?? new SystemClock();
    }
}
