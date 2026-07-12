<?php

/**
 * LoginUseCase — authenticate user credentials, establish session.
 *
 * Flow: validate input → find user → verify password → assert can login →
 *       resolve permissions → build UserContext → set session → audit → return result.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Application\DTOs\LoginRequest;
use WorkEddy\Modules\IAM\Application\DTOs\LoginResult;
use WorkEddy\Modules\IAM\Application\Services\AuthenticationThrottleService;
use WorkEddy\Modules\IAM\Application\Services\IAMAuthNotificationDispatcher;
use WorkEddy\Modules\IAM\Application\Services\UserContextFactory;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Modules\IAM\Infrastructure\OTPRepository;
use WorkEddy\Modules\IAM\Settings\IAMSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Clock\SystemClock;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\GatewayException;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use WorkEddy\Shared\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;

final class LoginUseCase
{
    public function __construct(
        private readonly IUserRepository       $userRepo,
        private readonly IPermissionRepository $permissionRepo,
        private readonly OTPRepository $otpRepo,
        private readonly AuthenticationThrottleService $throttle,
        private readonly ISessionService        $session,
        private readonly IAuditService          $audit,
        private readonly IAMSettings            $settings,
        private readonly LoggerInterface        $logger,
        private readonly IAMAuthNotificationDispatcher $authNotifications,
        private readonly UserContextFactory $contextFactory,
        private readonly ?IClock $clock = null,
    ) {}

    /**
     * @throws ValidationException If credentials are invalid.
     * @throws \WorkEddy\Shared\Exceptions\ForbiddenException If account is not active.
     */
    public function execute(LoginRequest $request): LoginResult
    {
        // Step 1: Input validation
        $errors = [];
        if (trim($request->email) === '') {
            $errors['email'] = 'Email is required.';
        }
        if (trim($request->password) === '') {
            $errors['password'] = 'Password is required.';
        }
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $identifier = strtolower(trim($request->email));
        $this->throttle->assertLoginAllowed($identifier);

        // Step 2: Find user by email
        $user = $this->userRepo->findByEmail($request->email);
        if ($user === null && filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            $user = $this->userRepo->findByEmail($request->email);
        }

        if ($user === null) {
            $this->throttle->recordFailedLogin($identifier);
            $this->logger->warning('Login failed: unknown email', [
                'emailHash' => hash('sha256', $identifier),
                'ipAddress' => $request->ipAddress,
            ]);
            throw new ValidationException(['credentials' => 'Invalid email or password.']);
        }

        // Step 3: Verify password
        if (!$user->verifyPassword($request->password)) {
            $this->throttle->recordFailedLogin($identifier);
            $this->logger->warning('Login failed: bad password', [
                'userId' => $user->getId(),
                'ipAddress' => $request->ipAddress,
            ]);
            $this->audit->record(
                action: 'iam.login.failed',
                entityType: 'User',
                entityId: (string) $user->getId(),
                afterState: ['reason' => 'invalid_password', 'ipAddress' => $request->ipAddress, 'module' => 'IAM'],
                actorId: (string) $user->getId(),
            );
            throw new ValidationException(['credentials' => 'Invalid username or password.']);
        }

        // Step 4: Assert account status
        try {
            $user->assertCanLogin();
        } catch (ForbiddenException $e) {
            $this->logger->warning('Login failed: account is not allowed to login', [
                'userId' => $user->getId(),
                'status' => $user->getStatus()->value,
                'ipAddress' => $request->ipAddress,
            ]);

            throw $e;
        }

        $this->throttle->clearFailedLogins($identifier);

        if (!$this->settings->authOtpEnabled()) {
            $this->otpRepo->invalidateAll($user->getId(), OTPRepository::PURPOSE_LOGIN);
            $this->throttle->clearOtpResendCooldown($user->getId());
            return $this->completeLoginWithoutOtp($user, $identifier, $request->ipAddress);
        }

        $this->otpRepo->invalidateAll($user->getId(), OTPRepository::PURPOSE_LOGIN);
        $code = (string) random_int(100000, 999999);
        $otpId = $this->otpRepo->create($user->getId(), $code, OTPRepository::PURPOSE_LOGIN);

        try {
            $this->authNotifications->sendLoginOtp($user, $code, (int) $otpId, $this->otpRepo->expiryMinutes());
        } catch (GatewayException $e) {
            $this->otpRepo->invalidateAll($user->getId(), OTPRepository::PURPOSE_LOGIN);
            $this->throttle->clearOtpResendCooldown($user->getId());
            throw $e;
        }

        $issuedAt = $this->clock()->now();
        $this->session->regenerate();
        $this->clearAuthenticationState();
        $this->session->set('pending_auth', [
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
            'challengeId' => bin2hex(random_bytes(16)),
            'ipAddress' => $request->ipAddress,
            'issuedAt' => $issuedAt->format('c'),
            'expiresAt' => $issuedAt->modify('+' . $this->otpRepo->expiryMinutes() . ' minutes')->format('c'),
        ]);
        $this->throttle->markOtpSent($user->getId());

        $this->audit->record(
            action: 'iam.login.challenge_issued',
            entityType: 'User',
            entityId: (string) $user->getId(),
            afterState: ['ipAddress' => $request->ipAddress, 'module' => 'IAM'],
            actorId: (string) $user->getId(),
        );

        $this->logger->info('Login password accepted; OTP challenge issued.', [
            'userId' => $user->getId(),
            'roleSlug' => $user->getRoleSlug(),
            'ipAddress' => $request->ipAddress,
        ]);

        return new LoginResult(
            userId: $user->getId(),
            userUuid: $user->getUuid(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            roleId: (int) $user->getEffectiveRoleId(),
            roleSlug: $user->getEffectiveRoleSlug(),
            privileges: [],
            loginAt: $issuedAt->format('c'),
            authzVersion: $user->getAuthzVersion(),
            platformRoleId: (int) $user->getRoleId(),
            platformRoleSlug: $user->getRoleSlug(),
            membershipRoleId: $user->getMembershipRoleId(),
            membershipRoleSlug: $user->getMembershipRoleSlug(),
            authenticated: false,
            requiresOtp: true,
            otpExpiresInMinutes: $this->otpRepo->expiryMinutes(),
        );
    }

    private function completeLoginWithoutOtp(User $user, string $identifier, ?string $ipAddress): LoginResult
    {
        $user->recordLogin();
        $this->userRepo->update($user);

        $loginAt = $this->clock()->now()->format('c');
        $ctx = $this->contextFactory->fromUser($user, $loginAt);

        $this->session->regenerate();
        $this->clearAuthenticationState();
        $this->session->set('email', $user->getEmail());
        $this->session->setUserContext($ctx);
        $this->throttle->clearFailedLogins($identifier);

        $this->audit->record(
            action: 'iam.login.success',
            entityType: 'User',
            entityId: (string) $user->getId(),
            afterState: ['ipAddress' => $ipAddress, 'module' => 'IAM'],
            actorId: (string) $user->getId(),
        );

        $this->logger->info('Login completed without OTP because OTP requirement is disabled.', [
            'userId' => $user->getId(),
            'roleSlug' => $user->getRoleSlug(),
            'ipAddress' => $ipAddress,
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

    private function clearAuthenticationState(): void
    {
        $this->session->set('pending_auth', null);
        $this->session->set('v2_user_context', null);
        $this->session->set('USER', null);
        $this->session->set('ROLE_TYPE', null);
        $this->session->set('privileges', []);
        $this->session->set('AUTHZ_VERSION', null);
    }

    private function clock(): IClock
    {
        return $this->clock ?? new SystemClock();
    }
}
