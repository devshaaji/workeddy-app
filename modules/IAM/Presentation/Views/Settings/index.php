<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'IAM Settings';
$pagePurpose = 'Configure password policy, session policy, and account defaults.';
$pageScripts = ['js/iam.js'];
$settings = $settings ?? [];
$can = $can ?? [];
$settingDefinitions = $settingDefinitions ?? [];
$e = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$default = static function (string $key, mixed $fallback = null) use ($settingDefinitions): mixed {
    foreach ($settingDefinitions as $definition) {
        if (($definition->key ?? null) === $key) {
            return $definition->default;
        }
    }

    return $fallback;
};
$checked = static fn(mixed $value): string => $value ? ' checked' : '';
$jsonDefault = static fn(string $key, mixed $fallback = []): string => htmlspecialchars(json_encode($default($key, $fallback), JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
$listValue = static function (mixed $value): string {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : preg_split('/[\r\n,]+/', $value);
    }
    if (!is_array($value)) {
        return '';
    }

    return implode("\n", array_values(array_filter(array_map(
        static fn(mixed $item): string => trim((string) $item),
        $value,
    ), static fn(string $item): bool => $item !== '')));
};
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<form id="iam-settings-form" class="row g-4" data-iam-screen="settings" method="POST" action="/api/v1/settings/iam">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0">Password Policy</h3>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label" for="min_password_length">Minimum password length</label>
                        <input type="number" id="min_password_length" name="min_password_length" class="form-control" value="<?= $e($settings['min_password_length'] ?? 8) ?>" data-default="<?= $e($default('min_password_length', 8)) ?>" min="6" max="128">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="min_username_length">Minimum username length</label>
                        <input type="number" id="min_username_length" name="min_username_length" class="form-control" value="<?= $e($settings['min_username_length'] ?? 3) ?>" data-default="<?= $e($default('min_username_length', 3)) ?>" min="2" max="32">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password_algorithm">Password hashing algorithm</label>
                        <input type="text" id="password_algorithm" class="form-control" value="<?= $e($settings['password_algorithm'] ?? 'argon2id') ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="argon2_memory_cost">Argon2 memory cost</label>
                        <input type="number" id="argon2_memory_cost" class="form-control" value="<?= $e($settings['argon2_memory_cost'] ?? 65536) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="argon2_time_cost">Argon2 time cost</label>
                        <input type="number" id="argon2_time_cost" class="form-control" value="<?= $e($settings['argon2_time_cost'] ?? 4) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="argon2_threads">Argon2 threads</label>
                        <input type="number" id="argon2_threads" class="form-control" value="<?= $e($settings['argon2_threads'] ?? 1) ?>" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0">Session Policy</h3>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label" for="session_lifetime_minutes">Session lifetime</label>
                        <div class="input-group">
                            <input type="number" id="session_lifetime_minutes" name="session_lifetime_minutes" class="form-control" value="<?= $e($settings['session_lifetime_minutes'] ?? 120) ?>" data-default="<?= $e($default('session_lifetime_minutes', 120)) ?>" min="5" max="1440">
                            <span class="input-group-text">minutes</span>
                        </div>
                        <div class="form-text">Configured maximum session lifetime setting.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="max_login_attempts">Max login attempts</label>
                        <input type="number" id="max_login_attempts" name="max_login_attempts" class="form-control" value="<?= $e($settings['max_login_attempts'] ?? 5) ?>" data-default="<?= $e($default('max_login_attempts', 5)) ?>" min="3" max="20">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="lockout_duration_minutes">Lockout duration</label>
                        <div class="input-group">
                            <input type="number" id="lockout_duration_minutes" name="lockout_duration_minutes" class="form-control" value="<?= $e($settings['lockout_duration_minutes'] ?? 15) ?>" data-default="<?= $e($default('lockout_duration_minutes', 15)) ?>" min="1" max="1440">
                            <span class="input-group-text">minutes</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="auth_session_idle_timeout">Idle timeout</label>
                        <div class="input-group">
                            <input type="number" id="auth_session_idle_timeout" name="auth.session_idle_timeout" class="form-control" value="<?= $e($settings['auth.session_idle_timeout'] ?? 1800) ?>" data-default="<?= $e($default('auth.session_idle_timeout', 1800)) ?>" min="60" max="86400">
                            <span class="input-group-text">seconds</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="auth_session_warning_threshold">Warning threshold</label>
                        <div class="input-group">
                            <input type="number" id="auth_session_warning_threshold" name="auth.session_warning_threshold" class="form-control" value="<?= $e($settings['auth.session_warning_threshold'] ?? 300) ?>" data-default="<?= $e($default('auth.session_warning_threshold', 300)) ?>" min="30" max="3600">
                            <span class="input-group-text">seconds</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="auth_session_absolute_timeout">Absolute timeout</label>
                        <div class="input-group">
                            <input type="number" id="auth_session_absolute_timeout" name="auth.session_absolute_timeout" class="form-control" value="<?= $e($settings['auth.session_absolute_timeout'] ?? 28800) ?>" data-default="<?= $e($default('auth.session_absolute_timeout', 28800)) ?>" min="300" max="604800">
                            <span class="input-group-text">seconds</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mt-md-6">
                            <input class="form-check-input" type="checkbox" id="auth_session_absolute_timeout_enabled" name="auth.session_absolute_timeout_enabled" data-default="<?= $default('auth.session_absolute_timeout_enabled', false) ? '1' : '0' ?>"<?= $checked($settings['auth.session_absolute_timeout_enabled'] ?? false) ?>>
                            <label class="form-check-label" for="auth_session_absolute_timeout_enabled">Enable absolute timeout</label>
                            <div class="form-text">Force re-authentication after the absolute timeout, even when active.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0">Authentication Controls</h3>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auth_otp_enabled" name="auth_otp_enabled" data-default="<?= $default('auth_otp_enabled', false) ? '1' : '0' ?>"<?= $checked($settings['auth_otp_enabled'] ?? false) ?>>
                            <label class="form-check-label" for="auth_otp_enabled">Require OTP at sign in</label>
                            <div class="form-text">Successful password login must be verified with a one-time code.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="password_reset_enabled" name="password_reset_enabled" data-default="<?= $default('password_reset_enabled', true) ? '1' : '0' ?>"<?= $checked($settings['password_reset_enabled'] ?? true) ?>>
                            <label class="form-check-label" for="password_reset_enabled">Allow password reset</label>
                            <div class="form-text">Users can request and complete password resets.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Account Defaults</h3>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="default_user_status_active" name="default_user_status_active" data-default="<?= $default('default_user_status_active', true) ? '1' : '0' ?>"<?= $checked(!empty($settings['default_user_status_active'])) ?>>
                            <label class="form-check-label" for="default_user_status_active">New users are active by default</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="public_registration_allowed_roles">Public registration allowed roles</label>
                        <textarea id="public_registration_allowed_roles" name="public_registration_allowed_roles" class="form-control" rows="3" data-setting-type="json-list" data-default="<?= $jsonDefault('public_registration_allowed_roles', []) ?>" placeholder="One role slug per line"><?= $e($listValue($settings['public_registration_allowed_roles'] ?? [])) ?></textarea>
                        <div class="form-text">Role slugs that public registration can request. Leave empty to disable public registration.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h3 class="card-title mb-0">IAM Notifications</h3>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notification_user_created_enabled" name="notifications.iam.user_created.enabled" data-default="<?= $default('notifications.iam.user_created.enabled', true) ? '1' : '0' ?>"<?= $checked($settings['notifications.iam.user_created.enabled'] ?? true) ?>>
                            <label class="form-check-label" for="notification_user_created_enabled">User account created</label>
                            <div class="form-text">Notify a user when their account is created.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notification_user_activated_enabled" name="notifications.iam.user_activated.enabled" data-default="<?= $default('notifications.iam.user_activated.enabled', true) ? '1' : '0' ?>"<?= $checked($settings['notifications.iam.user_activated.enabled'] ?? true) ?>>
                            <label class="form-check-label" for="notification_user_activated_enabled">User account activated</label>
                            <div class="form-text">Notify a user when their account is activated.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notification_user_suspended_enabled" name="notifications.iam.user_suspended.enabled" data-default="<?= $default('notifications.iam.user_suspended.enabled', true) ? '1' : '0' ?>"<?= $checked($settings['notifications.iam.user_suspended.enabled'] ?? true) ?>>
                            <label class="form-check-label" for="notification_user_suspended_enabled">User account suspended</label>
                            <div class="form-text">Notify a user when their account is suspended.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notification_role_assigned_enabled" name="notifications.iam.role_assigned.enabled" data-default="<?= $default('notifications.iam.role_assigned.enabled', true) ? '1' : '0' ?>"<?= $checked($settings['notifications.iam.role_assigned.enabled'] ?? true) ?>>
                            <label class="form-check-label" for="notification_role_assigned_enabled">Role assigned</label>
                            <div class="form-text">Notify a user when their role changes.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notification_force_logout_enabled" name="notifications.iam.force_logout.enabled" data-default="<?= $default('notifications.iam.force_logout.enabled', true) ? '1' : '0' ?>"<?= $checked($settings['notifications.iam.force_logout.enabled'] ?? true) ?>>
                            <label class="form-check-label" for="notification_force_logout_enabled">Sessions ended</label>
                            <div class="form-text">Notify a user when active sessions are ended.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notification_password_changed_enabled" name="notifications.iam.password_changed.enabled" data-default="<?= $default('notifications.iam.password_changed.enabled', true) ? '1' : '0' ?>"<?= $checked($settings['notifications.iam.password_changed.enabled'] ?? true) ?>>
                            <label class="form-check-label" for="notification_password_changed_enabled">Password changed</label>
                            <div class="form-text">Notify a user when their password is changed.</div>
                        </div>
                    </div>

                </div>
                <div id="iam-settings-feedback" class="d-none mt-4" data-form-feedback></div>
                <?php if (!empty($can['manageSettings'])): ?>
                    <div class="text-end mt-4">
                        <button type="button" class="btn btn-outline-secondary me-2" id="iam-settings-reset" data-iam-settings-reset>Reset Defaults</button>
                        <button type="submit" class="btn btn-primary" id="iam-settings-save">Save Settings</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>
