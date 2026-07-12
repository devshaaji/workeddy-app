<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Settings\IAMSettings;
use WorkEddy\Platform\Cache\ICacheService;
use WorkEddy\Shared\Exceptions\ValidationException;

final class AuthenticationThrottleService
{
    private const LOGIN_ATTEMPT_PREFIX = 'iam.auth.login_attempts.';
    private const LOGIN_LOCK_PREFIX = 'iam.auth.login_lock.';
    private const OTP_RESEND_PREFIX = 'iam.auth.otp.resend.';
    private const OTP_VERIFY_ATTEMPT_PREFIX = 'iam.auth.otp.verify_attempts.';
    private const OTP_VERIFY_LOCK_PREFIX = 'iam.auth.otp.verify_lock.';
    private const PASSWORD_RESET_PREFIX = 'iam.auth.password_reset.';
    public const OTP_RESEND_COOLDOWN_SECONDS = 60;
    public const PASSWORD_RESET_COOLDOWN_SECONDS = 60;
    private const OTP_VERIFY_MAX_ATTEMPTS = 3;
    private const OTP_VERIFY_LOCKOUT_SECONDS = 900; // 15 minutes

    public function __construct(
        private readonly ICacheService $cache,
        private readonly IAMSettings $settings,
    ) {}

    public function assertLoginAllowed(string $identifier): void
    {
        $state = $this->readArray(self::LOGIN_LOCK_PREFIX . $this->keyPart($identifier));
        $lockedUntil = (int) ($state['until'] ?? 0);

        if ($lockedUntil <= time()) {
            if ($lockedUntil > 0) {
                $this->cache->delete(self::LOGIN_LOCK_PREFIX . $this->keyPart($identifier));
            }

            return;
        }

        $minutes = max(1, (int) ceil(($lockedUntil - time()) / 60));
        throw new ValidationException([
            'credentials' => sprintf('Too many failed login attempts. Try again in %d minute(s).', $minutes),
        ]);
    }

    public function recordFailedLogin(string $identifier): void
    {
        $key = self::LOGIN_ATTEMPT_PREFIX . $this->keyPart($identifier);
        $ttl = max(60, $this->settings->lockoutDurationMinutes() * 60);
        $state = $this->readArray($key);
        $count = (int) ($state['count'] ?? 0) + 1;

        $this->cache->set($key, ['count' => $count], $ttl);

        if ($count < $this->settings->maxLoginAttempts()) {
            return;
        }

        $lockedUntil = time() + ($this->settings->lockoutDurationMinutes() * 60);
        $this->cache->set(self::LOGIN_LOCK_PREFIX . $this->keyPart($identifier), ['until' => $lockedUntil], $ttl);
        $this->cache->delete($key);
    }

    public function clearFailedLogins(string $identifier): void
    {
        $suffix = $this->keyPart($identifier);
        $this->cache->delete(self::LOGIN_ATTEMPT_PREFIX . $suffix);
        $this->cache->delete(self::LOGIN_LOCK_PREFIX . $suffix);
    }

    // --- OTP Verification Throttle ---

    /**
     * Assert that the user has not exceeded the maximum OTP verification attempts.
     * Prevents brute-force of 6-digit OTP codes.
     *
     * @throws ValidationException If verification attempts are exhausted.
     */
    public function assertOtpVerificationAllowed(int $userId): void
    {
        $lockState = $this->readArray(self::OTP_VERIFY_LOCK_PREFIX . $userId);
        $lockedUntil = (int) ($lockState['until'] ?? 0);

        if ($lockedUntil > time()) {
            $minutes = max(1, (int) ceil(($lockedUntil - time()) / 60));
            throw new ValidationException([
                'code' => sprintf('Too many failed OTP verification attempts. Try again in %d minute(s).', $minutes),
            ]);
        }

        if ($lockedUntil > 0) {
            $this->cache->delete(self::OTP_VERIFY_LOCK_PREFIX . $userId);
            $this->cache->delete(self::OTP_VERIFY_ATTEMPT_PREFIX . $userId);
        }
    }

    /**
     * Record a failed OTP verification attempt. Locks verification after
     * OTP_VERIFY_MAX_ATTEMPTS failures.
     */
    public function recordFailedOtpVerification(int $userId): void
    {
        $key = self::OTP_VERIFY_ATTEMPT_PREFIX . $userId;
        $state = $this->readArray($key);
        $count = (int) ($state['count'] ?? 0) + 1;

        $this->cache->set($key, ['count' => $count], self::OTP_VERIFY_LOCKOUT_SECONDS);

        if ($count >= self::OTP_VERIFY_MAX_ATTEMPTS) {
            $lockedUntil = time() + self::OTP_VERIFY_LOCKOUT_SECONDS;
            $this->cache->set(self::OTP_VERIFY_LOCK_PREFIX . $userId, ['until' => $lockedUntil], self::OTP_VERIFY_LOCKOUT_SECONDS);
            $this->cache->delete($key);
        }
    }

    /**
     * Clear OTP verification throttle on successful verification.
     */
    public function clearOtpVerificationAttempts(int $userId): void
    {
        $this->cache->delete(self::OTP_VERIFY_ATTEMPT_PREFIX . $userId);
        $this->cache->delete(self::OTP_VERIFY_LOCK_PREFIX . $userId);
    }

    // --- OTP Resend Cooldown ---

    public function assertOtpResendAllowed(int $userId): void
    {
        $state = $this->readArray(self::OTP_RESEND_PREFIX . $userId);
        $nextAllowedAt = (int) ($state['nextAllowedAt'] ?? 0);
        if ($nextAllowedAt <= time()) {
            if ($nextAllowedAt > 0) {
                $this->cache->delete(self::OTP_RESEND_PREFIX . $userId);
            }

            return;
        }

        throw new ValidationException([
            'userId' => sprintf('Please wait %d second(s) before requesting another OTP.', max(1, $nextAllowedAt - time())),
        ]);
    }

    public function markOtpSent(int $userId): void
    {
        $nextAllowedAt = time() + self::OTP_RESEND_COOLDOWN_SECONDS;
        $this->cache->set(
            self::OTP_RESEND_PREFIX . $userId,
            ['nextAllowedAt' => $nextAllowedAt],
            self::OTP_RESEND_COOLDOWN_SECONDS,
        );
    }

    public function clearOtpResendCooldown(int $userId): void
    {
        $this->cache->delete(self::OTP_RESEND_PREFIX . $userId);
    }

    // --- Password Reset Cooldown ---

    public function assertPasswordResetAllowed(string $identifier): void
    {
        $state = $this->readArray(self::PASSWORD_RESET_PREFIX . $this->keyPart($identifier));
        $nextAllowedAt = (int) ($state['nextAllowedAt'] ?? 0);
        if ($nextAllowedAt <= time()) {
            if ($nextAllowedAt > 0) {
                $this->cache->delete(self::PASSWORD_RESET_PREFIX . $this->keyPart($identifier));
            }

            return;
        }

        throw new ValidationException([
            'identifier' => sprintf('Please wait %d second(s) before requesting another password reset.', max(1, $nextAllowedAt - time())),
        ]);
    }

    public function markPasswordResetRequested(string $identifier): void
    {
        $nextAllowedAt = time() + self::PASSWORD_RESET_COOLDOWN_SECONDS;
        $this->cache->set(
            self::PASSWORD_RESET_PREFIX . $this->keyPart($identifier),
            ['nextAllowedAt' => $nextAllowedAt],
            self::PASSWORD_RESET_COOLDOWN_SECONDS,
        );
    }

    public function clearPasswordResetCooldown(string $identifier): void
    {
        $this->cache->delete(self::PASSWORD_RESET_PREFIX . $this->keyPart($identifier));
    }

    /**
     * @return array<string, mixed>
     */
    private function readArray(string $key): array
    {
        $value = $this->cache->get($key, static fn(): array => [], 60);

        return is_array($value) ? $value : [];
    }

    private function keyPart(string $identifier): string
    {
        return hash('sha256', strtolower(trim($identifier)));
    }
}
