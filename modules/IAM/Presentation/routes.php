<?php

/** IAM module route registrations. */

declare(strict_types=1);

use WorkEddy\Platform\Http\RouteRegistrar;
use WorkEddy\Modules\IAM\Presentation\AuthController;
use WorkEddy\Modules\IAM\Presentation\IAMBindingApiController;
use WorkEddy\Modules\IAM\Presentation\IAMPageController;
use WorkEddy\Modules\IAM\Presentation\PermissionController;
use WorkEddy\Modules\IAM\Presentation\RoleController;
use WorkEddy\Modules\IAM\Presentation\UserController;
use WorkEddy\Modules\IAM\Presentation\SettingsController;
use WorkEddy\Modules\IAM\Infrastructure\AuthRateLimitMiddleware;

return function (RouteRegistrar $routes): void {
    $uuid = '[0-9a-fA-F-]{36}';

    // Public web pages
    $routes->add('GET', '/login', [IAMPageController::class, 'login']);
    $routes->add('GET', '/register', [IAMPageController::class, 'register']);
    $routes->add('GET', '/forgot-password', [IAMPageController::class, 'forgotPassword']);
    $routes->add('GET', '/reset-password', [IAMPageController::class, 'resetPassword']);
    $routes->add('GET', '/verify-otp', [IAMPageController::class, 'verifyOtp']);
    $routes->add('GET', '/verify-email', [IAMPageController::class, 'verifyOtp']);
    $routes->add('GET', '/auth/login', [IAMPageController::class, 'login']);
    $routes->add('GET', '/logout', [IAMPageController::class, 'logout'], ['auth']);

    // Authenticated app web pages
    $routes->group('', function (RouteRegistrar $web) use ($uuid): void {
        $web->add('GET', '/users', [IAMPageController::class, 'users']);
        $web->add('GET', '/users/new', [IAMPageController::class, 'createUser']);
        $web->add('GET', '/users/pending-approvals', [IAMPageController::class, 'pendingApprovals']);
        $web->add('GET', '/users/{id:' . $uuid . '}', [IAMPageController::class, 'showUser']);
        $web->add('GET', '/users/{id:' . $uuid . '}/edit', [IAMPageController::class, 'editUser']);
        $web->add('GET', '/users/{id:' . $uuid . '}/role', [IAMPageController::class, 'assignUserRole']);
        $web->add('GET', '/users/{id:' . $uuid . '}/security', [IAMPageController::class, 'userSecurity']);

        $web->add('GET', '/roles', [IAMPageController::class, 'roles']);
        $web->add('GET', '/roles/new', [IAMPageController::class, 'createRole']);
        $web->add('GET', '/roles/{id:' . $uuid . '}', [IAMPageController::class, 'showRole']);
        $web->add('GET', '/roles/{id:' . $uuid . '}/edit', [IAMPageController::class, 'editRole']);
        $web->add('GET', '/roles/{id:' . $uuid . '}/permissions', [IAMPageController::class, 'assignPermissions']);

        $web->add('GET', '/permissions', [IAMPageController::class, 'permissions']);
        $web->add('GET', '/profile', [IAMPageController::class, 'profile']);
        $web->add('GET', '/profile/security', [IAMPageController::class, 'profileSecurity']);
        $web->add('GET', '/profile/sessions', [IAMPageController::class, 'profileSessions']);
        $web->add('GET', '/scope-error', [IAMPageController::class, 'wrongScope']);
        $web->add('GET', '/settings/page', [IAMPageController::class, 'settings']);
    }, ['auth']);

    $routes->group('/api/v1', function (RouteRegistrar $api) use ($uuid): void {
        // Public API endpoints (guest/auth bootstrap)
        $api->add('POST', '/auth/register', [AuthController::class, 'register'], ['iam_auth_rate_limit']);
        $api->add('POST', '/auth/login', [AuthController::class, 'login'], ['iam_auth_rate_limit']);
        $api->add('POST', '/auth/forgot-password', [AuthController::class, 'forgotPassword'], ['iam_auth_rate_limit']);
        $api->add('POST', '/auth/reset-password', [AuthController::class, 'resetPassword'], ['iam_auth_rate_limit']);
        $api->add('POST', '/auth/resend-otp', [AuthController::class, 'resendOtp'], ['iam_auth_rate_limit']);
        $api->add('POST', '/auth/verify-otp', [AuthController::class, 'verifyOtp'], ['iam_auth_rate_limit']);

        // Authenticated API endpoints
        $api->add('GET',   '/iam/users', [UserController::class, 'list'], ['auth']);
        $api->add('POST',  '/iam/users', [UserController::class, 'create'], ['auth']);
        $api->add('GET',   '/iam/users/pending-approvals', [IAMBindingApiController::class, 'pendingApprovals'], ['auth']);
        $api->add('GET',   '/iam/users/{id:' . $uuid . '}', [UserController::class, 'show'], ['auth']);
        $api->add('PUT',   '/iam/users/{id:' . $uuid . '}', [UserController::class, 'update'], ['auth']);
        $api->add('POST',  '/iam/users/{id:' . $uuid . '}/suspend', [UserController::class, 'suspend'], ['auth']);
        $api->add('POST',  '/iam/users/{id:' . $uuid . '}/activate', [UserController::class, 'activate'], ['auth']);
        $api->add('POST',  '/iam/users/{id:' . $uuid . '}/force-logout', [UserController::class, 'forceLogout'], ['auth']);
        $api->add('DELETE', '/iam/users/{id:' . $uuid . '}', [UserController::class, 'delete'], ['auth']);
        $api->add('PUT',   '/iam/users/{id:' . $uuid . '}/password', [UserController::class, 'changePassword'], ['auth']);
        $api->add('PUT',   '/iam/users/{id:' . $uuid . '}/role', [UserController::class, 'assignRole'], ['auth']);
        $api->add('GET',   '/iam/users/{id:' . $uuid . '}/permissions', [IAMBindingApiController::class, 'userPermissions'], ['auth']);
        $api->add('PUT',   '/iam/users/{id:' . $uuid . '}/permissions', [IAMBindingApiController::class, 'userPermissions'], ['auth']);
        $api->add('GET',   '/iam/users/{id:' . $uuid . '}/sessions', [IAMBindingApiController::class, 'userSessions'], ['auth']);
        $api->add('DELETE', '/iam/users/{id:' . $uuid . '}/sessions/{sessionId}', [IAMBindingApiController::class, 'revokeUserSession'], ['auth']);

        $api->add('GET',   '/iam/roles', [RoleController::class, 'list'], ['auth']);
        $api->add('POST',  '/iam/roles', [RoleController::class, 'pendingMutation'], ['auth']);
        $api->add('GET',   '/iam/roles/{id:' . $uuid . '}', [RoleController::class, 'show'], ['auth']);
        $api->add('PUT',   '/iam/roles/{id:' . $uuid . '}', [RoleController::class, 'pendingMutation'], ['auth']);
        $api->add('PUT',   '/iam/roles/{id:' . $uuid . '}/permissions', [RoleController::class, 'assignPermissions'], ['auth']);
        $api->add('GET',   '/iam/permissions', [PermissionController::class, 'list'], ['auth']);

        $api->add('GET',   '/iam/profile', [IAMBindingApiController::class, 'profile'], ['auth']);
        $api->add('POST',  '/iam/profile/tenant', [IAMBindingApiController::class, 'switchTenant'], ['auth', 'csrf']);
        $api->add('PUT',   '/iam/profile/password', [IAMBindingApiController::class, 'updateProfilePassword'], ['auth']);
        $api->add('GET',   '/iam/profile/activity', [IAMBindingApiController::class, 'profileActivity'], ['auth']);
        $api->add('GET',   '/iam/profile/sessions', [IAMBindingApiController::class, 'profileSessions'], ['auth']);
        $api->add('DELETE', '/iam/profile/sessions/{sessionId}', [IAMBindingApiController::class, 'revokeProfileSession'], ['auth']);

        $api->add('GET',  '/auth/session-status', [AuthController::class, 'sessionStatus'], ['auth']);
        $api->add('POST', '/auth/heartbeat', [AuthController::class, 'heartbeat'], ['auth', 'csrf']);
        $api->add('POST', '/auth/session-activity', [AuthController::class, 'sessionActivity'], ['auth', 'csrf']);
        $api->add('POST', '/auth/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);

        $api->add('GET',    '/settings', [SettingsController::class, 'index'], ['auth']);
        $api->add('PUT',    '/settings/{module:[a-zA-Z0-9_]+}', [SettingsController::class, 'updateModule'], ['auth']);
        $api->add('DELETE', '/settings/{module:[a-zA-Z0-9_]+}', [SettingsController::class, 'resetModule'], ['auth']);
        $api->add('PUT',    '/settings/{module:[a-zA-Z0-9_]+}/{key:[a-zA-Z0-9_.]+}', [SettingsController::class, 'update'], ['auth']);
        $api->add('DELETE', '/settings/{module:[a-zA-Z0-9_]+}/{key:[a-zA-Z0-9_.]+}', [SettingsController::class, 'reset'], ['auth']);
    });
};
