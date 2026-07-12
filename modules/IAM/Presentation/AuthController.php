<?php

/**
 * Auth controller — thin HTTP adapter. No business logic.
 *
 * Reads request → calls use case → returns response array.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Presentation;

use WorkEddy\Modules\IAM\Application\LoginUseCase;
use WorkEddy\Modules\IAM\Application\LogoutUseCase;
use WorkEddy\Modules\IAM\Application\PublicRegisterUseCase;
use WorkEddy\Modules\IAM\Application\RequestPasswordResetUseCase;
use WorkEddy\Modules\IAM\Application\ResendOTPUseCase;
use WorkEddy\Modules\IAM\Application\ResetPasswordUseCase;
use WorkEddy\Modules\IAM\Application\VerifyOTPUseCase;
use WorkEddy\Modules\IAM\Application\Services\AuthenticationThrottleService;
use WorkEddy\Modules\IAM\Application\DTOs\LoginRequest;
use WorkEddy\Modules\IAM\Application\DTOs\PublicRegisterRequest;
use WorkEddy\Modules\IAM\Application\DTOs\VerifyOTPRequest;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\SessionSecurityService;
use WorkEddy\Shared\Support\UuidSupport;

final class AuthController
{
    public function __construct(
        private readonly LoginUseCase     $loginUseCase,
        private readonly LogoutUseCase    $logoutUseCase,
        private readonly VerifyOTPUseCase $verifyOTPUseCase,
        private readonly RequestPasswordResetUseCase $requestPasswordResetUseCase,
        private readonly ResendOTPUseCase $resendOTPUseCase,
        private readonly ResetPasswordUseCase $resetPasswordUseCase,
        private readonly PublicRegisterUseCase $publicRegisterUseCase,
        private readonly IUserRepository $users,
        private readonly ISessionService  $session,
        private readonly SessionSecurityService $sessionSecurity,
    ) {}

    public function login(Request $request): array
    {
        $body = $this->requestData($request);

        $result = $this->loginUseCase->execute(new LoginRequest(
            email: (string) ($body['email'] ?? $body['username'] ?? ''),
            password: $body['password'] ?? '',
            ipAddress: $request->getClientIp(),
        ));

        return [
            'status' => 'ok',
            'data'   => [
                'authenticated' => $result->authenticated,
                'requiresOtp' => $result->requiresOtp,
                'userId'   => $result->userUuid,
                'otpExpiresInMinutes' => $result->otpExpiresInMinutes,
            ],
        ];
    }

    public function logout(Request $request): array
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return ['status' => 'error', 'message' => 'Unauthenticated'];
        }
        $this->logoutUseCase->execute($ctx);

        return ['status' => 'ok'];
    }

    public function sessionStatus(Request $request): array
    {
        $status = $this->sessionSecurity->status();
        unset($status['expired']);

        return ['status' => 'ok', 'ok' => true, 'data' => $status];
    }

    public function heartbeat(Request $request): array
    {
        $status = $this->sessionSecurity->heartbeat();
        unset($status['expired']);

        return ['status' => 'ok', 'ok' => true, 'data' => $status];
    }

    public function sessionActivity(Request $request): array
    {
        $status = $this->sessionSecurity->refreshActivity($request);
        unset($status['expired']);

        return ['status' => 'ok', 'ok' => true, 'data' => $status];
    }

    public function register(Request $request): array
    {
        $body = $this->requestData($request);
        $user = $this->publicRegisterUseCase->execute(new PublicRegisterRequest(
            email: (string) ($body['email'] ?? ''),
            fullName: (string) ($body['fullName'] ?? $body['full_name'] ?? $body['name'] ?? ''),
            password: (string) ($body['password'] ?? ''),
            organizationName: (string) ($body['organizationName'] ?? $body['organization_name'] ?? $body['workspaceName'] ?? $body['workspace_name'] ?? ''),
            phone: isset($body['phone']) ? (string) $body['phone'] : null,
            ipAddress: $request->getClientIp(),
        ));

        return [
            'status' => 'ok',
            'data' => [
                'userId' => $user->getUuid(),
                'email' => $user->getEmail(),
                'status' => $user->getStatus()->value,
            ],
        ];
    }

    public function forgotPassword(Request $request): array
    {
        $body = $this->requestData($request);
        $this->requestPasswordResetUseCase->execute((string) ($body['identifier'] ?? $body['email'] ?? $body['username'] ?? ''));

        $data = [
            'accepted' => true,
            'message' => 'If the account is active, a password reset link has been sent to the registered email address.',
        ];

        return ['status' => 'ok', 'data' => $data];
    }

    public function resetPassword(Request $request): array
    {
        $body = $this->requestData($request);
        $this->resetPasswordUseCase->execute(
            $this->resolveUserId(
                $body['userId'] ?? $body['user_id'] ?? null,
                $body['userUuid'] ?? $body['user_uuid'] ?? null,
            ),
            (string) ($body['code'] ?? $body['otp'] ?? ''),
            (string) ($body['newPassword'] ?? $body['new_password'] ?? $body['password'] ?? ''),
        );

        return ['status' => 'ok'];
    }

    public function resendOtp(Request $request): array
    {
        $body = $this->requestData($request);
        $result = $this->resendOTPUseCase->execute($this->resolveUserId(
            $body['userId'] ?? $body['user_id'] ?? null,
            $body['userUuid'] ?? $body['user_uuid'] ?? null,
        ), $request->getClientIp());

        $data = [
            'userId' => $result['userUuid'],
            'resendCooldownSeconds' => AuthenticationThrottleService::OTP_RESEND_COOLDOWN_SECONDS,
        ];

        return ['status' => 'ok', 'data' => $data];
    }

    public function verifyOtp(Request $request): array
    {
        $body = $this->requestData($request);

        $result = $this->verifyOTPUseCase->execute(new VerifyOTPRequest(
            userId: $this->resolveUserId(
                $body['userId'] ?? $body['user_id'] ?? null,
                $body['userUuid'] ?? $body['user_uuid'] ?? null,
            ),
            code: $body['code'] ?? '',
            ipAddress: $request->getClientIp(),
        ));

        return [
            'status' => 'ok',
            'data'   => [
                'authenticated' => $result->authenticated,
                'requiresOtp' => $result->requiresOtp,
                'userId'   => $result->userUuid,
            ],
        ];
    }

    private function resolveUserId(mixed $userId, mixed $userUuid): int
    {
        $id = is_numeric($userId) ? (int) $userId : 0;
        if ($id > 0) {
            return $id;
        }

        $uuid = is_string($userUuid) ? trim($userUuid) : '';
        if ($uuid === '') {
            return 0;
        }

        $user = $this->users->findByUuid(UuidSupport::requireValid($uuid, 'userUuid'));
        return $user?->getId() !== null ? (int) $user->getId() : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(Request $request): array
    {
        return array_replace($request->body, $request->json);
    }
}
