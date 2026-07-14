<?php

/**
 * IAM module settings provider — declares all IAM-owned setting definitions.
 *
 * Registered with SettingsRegistry during module boot.
 * Each definition includes type, default, validation, and metadata.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class IAMSettingsProvider implements IModuleSettingsProvider, \WorkEddy\Platform\Settings\ISettingsPageProvider
{
    public function getModuleName(): string
    {
        return 'iam';
    }

    /** @return SettingDefinition[] */
    public function getDefinitions(): array
    {
        return [
            // --- Password Policy ---
            new SettingDefinition(
                key: 'min_password_length',
                module: 'iam',
                type: SettingType::INTEGER,
                default: 8,
                label: 'Minimum Password Length',
                description: 'Minimum number of characters required for user passwords.',
                validation: fn($v) => (int) $v >= 6 && (int) $v <= 128
                    ? true : 'Must be between 6 and 128 characters.',
                section: 'Password Policy',
            ),
            new SettingDefinition(
                key: 'password_algorithm',
                module: 'iam',
                type: SettingType::STRING,
                default: 'argon2id',
                label: 'Password Hashing Algorithm',
                description: 'Algorithm used for hashing passwords (argon2id or bcrypt).',
                validation: fn($v) => in_array($v, ['argon2id', 'bcrypt'], true)
                    ? true : 'Must be argon2id or bcrypt.',
                editable: false, // changing algorithm requires careful migration
                restartRequired: true,
                section: 'Password Policy',
            ),
            new SettingDefinition(
                key: 'argon2_memory_cost',
                module: 'iam',
                type: SettingType::INTEGER,
                default: 65536,
                label: 'Argon2 Memory Cost (KiB)',
                description: 'Memory cost parameter for Argon2 password hashing.',
                validation: fn($v) => (int) $v >= 8192 && (int) $v <= 1048576
                    ? true : 'Must be between 8192 and 1048576 KiB.',
                editable: false,
                restartRequired: true,
                section: 'Password Policy',
            ),
            new SettingDefinition(
                key: 'argon2_time_cost',
                module: 'iam',
                type: SettingType::INTEGER,
                default: 4,
                label: 'Argon2 Time Cost',
                description: 'Number of iterations for Argon2 password hashing.',
                validation: fn($v) => (int) $v >= 1 && (int) $v <= 16
                    ? true : 'Must be between 1 and 16.',
                editable: false,
                restartRequired: true,
                section: 'Password Policy',
            ),
            new SettingDefinition(
                key: 'argon2_threads',
                module: 'iam',
                type: SettingType::INTEGER,
                default: 1,
                label: 'Argon2 Threads',
                description: 'Parallelism factor for Argon2 password hashing.',
                validation: fn($v) => (int) $v >= 1 && (int) $v <= 4
                    ? true : 'Must be between 1 and 4.',
                editable: false,
                restartRequired: true,
                section: 'Password Policy',
            ),

            // --- Session Policy ---
            new SettingDefinition(
                key: 'session_lifetime_minutes',
                module: 'iam',
                type: SettingType::INTEGER,
                default: 120,
                label: 'Session Lifetime (minutes)',
                description: 'How long a user session remains valid before requiring re-authentication.',
                validation: fn($v) => (int) $v >= 5 && (int) $v <= 1440
                    ? true : 'Must be between 5 and 1440 minutes (24 hours).',
                section: 'Session Policy',
            ),
            new SettingDefinition(
                key: 'max_login_attempts',
                module: 'iam',
                type: SettingType::INTEGER,
                default: 5,
                label: 'Max Login Attempts',
                description: 'Maximum number of failed login attempts before account lockout.',
                validation: fn($v) => (int) $v >= 3 && (int) $v <= 20
                    ? true : 'Must be between 3 and 20.',
                section: 'Session Policy',
            ),
            new SettingDefinition(
                key: 'lockout_duration_minutes',
                module: 'iam',
                type: SettingType::INTEGER,
                default: 15,
                label: 'Lockout Duration (minutes)',
                description: 'Minutes to lock an account after exceeding max login attempts.',
                validation: fn($v) => (int) $v >= 1 && (int) $v <= 1440
                    ? true : 'Must be between 1 and 1440 minutes.',
                section: 'Session Policy',
            ),

            // --- Authentication Rate Limiting ---
            new SettingDefinition(
                key: IAMSettings::AUTH_RATE_LIMIT_WINDOW_SECONDS,
                module: 'iam',
                type: SettingType::INTEGER,
                default: 300,
                label: 'Auth Rate Limit Window',
                description: 'Number of seconds used for authentication endpoint rate limit windows.',
                validation: fn($v) => (int) $v >= 10 && (int) $v <= 3600
                    ? true : 'Must be between 10 and 3600 seconds.',
                section: 'Authentication Rate Limiting',
            ),
            new SettingDefinition(
                key: IAMSettings::AUTH_RATE_LIMIT_LOGIN_IP,
                module: 'iam',
                type: SettingType::INTEGER,
                default: 10,
                label: 'Login Attempts Per IP',
                description: 'Maximum login requests per IP address in the auth rate limit window.',
                validation: fn($v) => (int) $v >= 0 && (int) $v <= 1000
                    ? true : 'Must be between 0 and 1000.',
                section: 'Authentication Rate Limiting',
            ),
            new SettingDefinition(
                key: IAMSettings::AUTH_RATE_LIMIT_LOGIN_ACCOUNT,
                module: 'iam',
                type: SettingType::INTEGER,
                default: 10,
                label: 'Login Attempts Per Account',
                description: 'Maximum login requests per account identifier in the auth rate limit window.',
                validation: fn($v) => (int) $v >= 0 && (int) $v <= 1000
                    ? true : 'Must be between 0 and 1000.',
                section: 'Authentication Rate Limiting',
            ),
            new SettingDefinition(
                key: IAMSettings::AUTH_RATE_LIMIT_REGISTER_IP,
                module: 'iam',
                type: SettingType::INTEGER,
                default: 5,
                label: 'Registration Attempts Per IP',
                description: 'Maximum registration requests per IP address in the auth rate limit window.',
                validation: fn($v) => (int) $v >= 0 && (int) $v <= 1000
                    ? true : 'Must be between 0 and 1000.',
                section: 'Authentication Rate Limiting',
            ),
            new SettingDefinition(
                key: IAMSettings::AUTH_RATE_LIMIT_REGISTER_ACCOUNT,
                module: 'iam',
                type: SettingType::INTEGER,
                default: 5,
                label: 'Registration Attempts Per Account',
                description: 'Maximum registration requests per account identifier in the auth rate limit window.',
                validation: fn($v) => (int) $v >= 0 && (int) $v <= 1000
                    ? true : 'Must be between 0 and 1000.',
                section: 'Authentication Rate Limiting',
            ),
            new SettingDefinition(
                key: IAMSettings::AUTH_RATE_LIMIT_PASSWORD_IP,
                module: 'iam',
                type: SettingType::INTEGER,
                default: 5,
                label: 'Password Flow Attempts Per IP',
                description: 'Maximum password reset requests per IP address in the auth rate limit window.',
                validation: fn($v) => (int) $v >= 0 && (int) $v <= 1000
                    ? true : 'Must be between 0 and 1000.',
                section: 'Authentication Rate Limiting',
            ),
            new SettingDefinition(
                key: IAMSettings::AUTH_RATE_LIMIT_PASSWORD_ACCOUNT,
                module: 'iam',
                type: SettingType::INTEGER,
                default: 5,
                label: 'Password Flow Attempts Per Account',
                description: 'Maximum password reset requests per account identifier in the auth rate limit window.',
                validation: fn($v) => (int) $v >= 0 && (int) $v <= 1000
                    ? true : 'Must be between 0 and 1000.',
                section: 'Authentication Rate Limiting',
            ),
            new SettingDefinition(
                key: IAMSettings::AUTH_RATE_LIMIT_OTP_IP,
                module: 'iam',
                type: SettingType::INTEGER,
                default: 3,
                label: 'OTP Attempts Per IP',
                description: 'Maximum OTP requests per IP address in the auth rate limit window.',
                validation: fn($v) => (int) $v >= 0 && (int) $v <= 1000
                    ? true : 'Must be between 0 and 1000.',
                section: 'Authentication Rate Limiting',
            ),
            new SettingDefinition(
                key: IAMSettings::AUTH_RATE_LIMIT_OTP_ACCOUNT,
                module: 'iam',
                type: SettingType::INTEGER,
                default: 3,
                label: 'OTP Attempts Per Account',
                description: 'Maximum OTP requests per account identifier in the auth rate limit window.',
                validation: fn($v) => (int) $v >= 0 && (int) $v <= 1000
                    ? true : 'Must be between 0 and 1000.',
                section: 'Authentication Rate Limiting',
            ),

            // --- Authentication Controls ---
            new SettingDefinition(
                key: IAMSettings::AUTH_OTP_ENABLED,
                module: 'iam',
                type: SettingType::BOOLEAN,
                default: false,
                label: 'Require OTP at Sign In',
                description: 'Whether successful password login must be verified with a one-time code.',
                section: 'Authentication Controls',
            ),
            new SettingDefinition(
                key: IAMSettings::PASSWORD_RESET_ENABLED,
                module: 'iam',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Allow Password Reset',
                description: 'Whether users can request and complete password resets.',
                section: 'Authentication Controls',
            ),
            new SettingDefinition(
                key: IAMSettings::AUTH_SESSION_IDLE_TIMEOUT,
                module: 'iam',
                type: SettingType::INTEGER,
                default: 1800,
                label: 'Session Idle Timeout',
                description: 'Seconds of inactivity allowed before a session expires.',
                validation: fn($v) => (int) $v >= 60 && (int) $v <= 86400
                    ? true : 'Must be between 60 and 86400 seconds.',
                section: 'Authentication Controls',
            ),
            new SettingDefinition(
                key: IAMSettings::AUTH_SESSION_WARNING_THRESHOLD,
                module: 'iam',
                type: SettingType::INTEGER,
                default: 300,
                label: 'Session Warning Threshold',
                description: 'Seconds before idle expiry when the browser should warn the user.',
                validation: fn($v) => (int) $v >= 30 && (int) $v <= 3600
                    ? true : 'Must be between 30 and 3600 seconds.',
                section: 'Authentication Controls',
            ),
            new SettingDefinition(
                key: IAMSettings::AUTH_SESSION_ABSOLUTE_TIMEOUT_ENABLED,
                module: 'iam',
                type: SettingType::BOOLEAN,
                default: false,
                label: 'Enable Session Absolute Timeout',
                description: 'Whether sessions must expire after a fixed maximum age regardless of user activity.',
                section: 'Authentication Controls',
            ),
            new SettingDefinition(
                key: IAMSettings::AUTH_SESSION_ABSOLUTE_TIMEOUT,
                module: 'iam',
                type: SettingType::INTEGER,
                default: 28800,
                label: 'Session Absolute Timeout',
                description: 'Maximum session age in seconds, regardless of activity.',
                validation: fn($v) => (int) $v >= 300 && (int) $v <= 604800
                    ? true : 'Must be between 300 and 604800 seconds.',
                section: 'Authentication Controls',
            ),

            // --- Account Management ---
            new SettingDefinition(
                key: 'default_user_status_active',
                module: 'iam',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Default User Status Active',
                description: 'Whether newly created users are ACTIVE by default (false = PENDING).',
                section: 'Account Management',
            ),
            new SettingDefinition(
                key: 'min_username_length',
                module: 'iam',
                type: SettingType::INTEGER,
                default: 3,
                label: 'Minimum Username Length',
                description: 'Minimum number of characters for usernames.',
                validation: fn($v) => (int) $v >= 2 && (int) $v <= 32
                    ? true : 'Must be between 2 and 32.',
                section: 'Account Management',
            ),

            // --- Public Registration ---
            new SettingDefinition(
                key: 'public_registration_allowed_roles',
                module: 'iam',
                type: SettingType::JSON,
                default: ['organization_admin'],
                label: 'Public Registration Allowed Roles',
                description: 'List of role slugs that public registration can request. Empty list disables public registration until roles are explicitly allowlisted.',
                section: 'Public Registration',
            ),

            // --- Notifications ---
            new SettingDefinition(
                key: 'notifications.iam.user_created.enabled',
                module: 'iam',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'User Created Notification',
                description: 'Notify a user when their account is created.',
                section: 'Notifications',
            ),
            new SettingDefinition(
                key: 'notifications.iam.user_activated.enabled',
                module: 'iam',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'User Activated Notification',
                description: 'Notify a user when their account is activated.',
                section: 'Notifications',
            ),
            new SettingDefinition(
                key: 'notifications.iam.user_suspended.enabled',
                module: 'iam',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'User Suspended Notification',
                description: 'Notify a user when their account is suspended.',
                section: 'Notifications',
            ),
            new SettingDefinition(
                key: 'notifications.iam.role_assigned.enabled',
                module: 'iam',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Role Assigned Notification',
                description: 'Notify a user when their role changes.',
                section: 'Notifications',
            ),
            new SettingDefinition(
                key: 'notifications.iam.force_logout.enabled',
                module: 'iam',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Force Logout Notification',
                description: 'Notify a user when active sessions are ended.',
                section: 'Notifications',
            ),
            new SettingDefinition(
                key: 'notifications.iam.password_changed.enabled',
                module: 'iam',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Password Changed Notification',
                description: 'Notify a user when their password is changed.',
                section: 'Notifications',
            ),
        ];
    }

    public function getSettingsPageMetadata(): \WorkEddy\Platform\Settings\SettingsPageMetadata
    {
        return new \WorkEddy\Platform\Settings\SettingsPageMetadata(
            module: 'iam',
            label: 'IAM',
            viewPermissions: [\WorkEddy\Modules\IAM\Authorization\IAMPermissions::SETTINGS_MANAGE],
            editPermissions: [\WorkEddy\Modules\IAM\Authorization\IAMPermissions::SETTINGS_MANAGE],
            sortOrder: 100,
        );
    }
}
