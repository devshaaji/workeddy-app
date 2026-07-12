<?php

/**
 * IAM module settings — typed accessors for IAM runtime configuration.
 *
 * Controls password policy, session policy, and account management behavior.
 * These are runtime-configurable settings owned by the IAM module.
 *
 * Infrastructure secrets (DB credentials, etc.) remain in .env — NOT here.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class IAMSettings extends ModuleSettings
{
    public const AUTH_OTP_ENABLED = 'auth_otp_enabled';
    public const PASSWORD_RESET_ENABLED = 'password_reset_enabled';
    public const AUTH_SESSION_IDLE_TIMEOUT = 'auth.session_idle_timeout';
    public const AUTH_SESSION_WARNING_THRESHOLD = 'auth.session_warning_threshold';
    public const AUTH_SESSION_ABSOLUTE_TIMEOUT_ENABLED = 'auth.session_absolute_timeout_enabled';
    public const AUTH_SESSION_ABSOLUTE_TIMEOUT = 'auth.session_absolute_timeout';
    public const AUTH_RATE_LIMIT_WINDOW_SECONDS = 'auth_rate_limit.window_seconds';
    public const AUTH_RATE_LIMIT_LOGIN_IP = 'auth_rate_limit.login.ip';
    public const AUTH_RATE_LIMIT_LOGIN_ACCOUNT = 'auth_rate_limit.login.account';
    public const AUTH_RATE_LIMIT_REGISTER_IP = 'auth_rate_limit.register.ip';
    public const AUTH_RATE_LIMIT_REGISTER_ACCOUNT = 'auth_rate_limit.register.account';
    public const AUTH_RATE_LIMIT_PASSWORD_IP = 'auth_rate_limit.password.ip';
    public const AUTH_RATE_LIMIT_PASSWORD_ACCOUNT = 'auth_rate_limit.password.account';
    public const AUTH_RATE_LIMIT_OTP_IP = 'auth_rate_limit.otp.ip';
    public const AUTH_RATE_LIMIT_OTP_ACCOUNT = 'auth_rate_limit.otp.account';

    protected function moduleName(): string
    {
        return 'iam';
    }

    // =========================================================================
    // PASSWORD POLICY
    // =========================================================================

    /** Minimum password length. */
    public function minPasswordLength(): int
    {
        return $this->getInt('min_password_length');
    }

    /** Password hashing algorithm (PASSWORD_ARGON2ID, PASSWORD_BCRYPT). */
    public function passwordAlgorithm(): string
    {
        return $this->getString('password_algorithm');
    }

    /** Password hashing algorithm constant used by password_hash(). */
    public function passwordAlgorithmConstant(): string
    {
        return $this->passwordAlgorithm() === 'bcrypt'
            ? PASSWORD_BCRYPT
            : PASSWORD_ARGON2ID;
    }

    /** Password hashing options for the configured algorithm. */
    public function passwordHashOptions(): array
    {
        if ($this->passwordAlgorithm() === 'bcrypt') {
            return [];
        }

        return [
            'memory_cost' => $this->argon2MemoryCost(),
            'time_cost'   => $this->argon2TimeCost(),
            'threads'     => $this->argon2Threads(),
        ];
    }

    /** Argon2 memory cost in KiB. */
    public function argon2MemoryCost(): int
    {
        return $this->getInt('argon2_memory_cost');
    }

    /** Argon2 time cost (iterations). */
    public function argon2TimeCost(): int
    {
        return $this->getInt('argon2_time_cost');
    }

    /** Argon2 threads (parallelism). */
    public function argon2Threads(): int
    {
        return $this->getInt('argon2_threads');
    }

    // =========================================================================
    // SESSION POLICY
    // =========================================================================

    /** Session lifetime in minutes. */
    public function sessionLifetimeMinutes(): int
    {
        return $this->getInt('session_lifetime_minutes');
    }

    /** Maximum failed login attempts before lockout. */
    public function maxLoginAttempts(): int
    {
        return $this->getInt('max_login_attempts');
    }

    /** Lockout duration in minutes after max failed attempts. */
    public function lockoutDurationMinutes(): int
    {
        return $this->getInt('lockout_duration_minutes');
    }

    // =========================================================================
    // AUTHENTICATION FEATURES
    // =========================================================================

    public function authOtpEnabled(): bool
    {
        return $this->getBool(self::AUTH_OTP_ENABLED);
    }

    public function passwordResetEnabled(): bool
    {
        return $this->getBool(self::PASSWORD_RESET_ENABLED);
    }

    public function sessionIdleTimeoutSeconds(): int
    {
        return max(60, $this->getInt(self::AUTH_SESSION_IDLE_TIMEOUT));
    }

    public function sessionWarningThresholdSeconds(): int
    {
        return max(30, min(
            $this->sessionIdleTimeoutSeconds() - 1,
            $this->getInt(self::AUTH_SESSION_WARNING_THRESHOLD),
        ));
    }

    public function sessionAbsoluteTimeoutEnabled(): bool
    {
        return $this->getBool(self::AUTH_SESSION_ABSOLUTE_TIMEOUT_ENABLED);
    }

    public function sessionAbsoluteTimeoutSeconds(): int
    {
        return max($this->sessionIdleTimeoutSeconds(), $this->getInt(self::AUTH_SESSION_ABSOLUTE_TIMEOUT));
    }

    // =========================================================================
    // ACCOUNT MANAGEMENT
    // =========================================================================

    /** Whether new users default to ACTIVE status. */
    public function defaultUserStatusActive(): bool
    {
        return $this->getBool('default_user_status_active');
    }

    /** Minimum username length. */
    public function minUsernameLength(): int
    {
        return $this->getInt('min_username_length');
    }

    // =========================================================================
    // PUBLIC REGISTRATION
    // =========================================================================

    /**
     * Allowlisted role slugs for public registration.
     * Empty means public registration is disabled until an allowlist is configured.
     *
     * @return string[]
     */
    public function publicRegistrationAllowedRoles(): array
    {
        $val = $this->getJson('public_registration_allowed_roles');
        return array_values(array_filter(array_map('trim', $val), fn(string $s) => $s !== ''));
    }

    public function authRateLimitWindowSeconds(): int
    {
        return max(10, $this->getInt(self::AUTH_RATE_LIMIT_WINDOW_SECONDS));
    }

    public function authRateLimitLoginIp(): int
    {
        return max(0, $this->getInt(self::AUTH_RATE_LIMIT_LOGIN_IP));
    }

    public function authRateLimitLoginAccount(): int
    {
        return max(0, $this->getInt(self::AUTH_RATE_LIMIT_LOGIN_ACCOUNT));
    }

    public function authRateLimitRegisterIp(): int
    {
        return max(0, $this->getInt(self::AUTH_RATE_LIMIT_REGISTER_IP));
    }

    public function authRateLimitRegisterAccount(): int
    {
        return max(0, $this->getInt(self::AUTH_RATE_LIMIT_REGISTER_ACCOUNT));
    }

    public function authRateLimitPasswordIp(): int
    {
        return max(0, $this->getInt(self::AUTH_RATE_LIMIT_PASSWORD_IP));
    }

    public function authRateLimitPasswordAccount(): int
    {
        return max(0, $this->getInt(self::AUTH_RATE_LIMIT_PASSWORD_ACCOUNT));
    }

    public function authRateLimitOtpIp(): int
    {
        return max(0, $this->getInt(self::AUTH_RATE_LIMIT_OTP_IP));
    }

    public function authRateLimitOtpAccount(): int
    {
        return max(0, $this->getInt(self::AUTH_RATE_LIMIT_OTP_ACCOUNT));
    }
}
