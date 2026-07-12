<?php

declare(strict_types=1);

use WorkEddy\Platform\Http\RouteRegistrar;
use WorkEddy\Modules\Notification\Presentation\NotificationApiController;

return function (RouteRegistrar $routes): void {
    $uuidPattern = '[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[1-5][0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}';

    // Authenticated Web Pages
    $routes->group('', function (RouteRegistrar $web) {
        $web->add('GET', '/notifications/logs', [\WorkEddy\Modules\Notification\Presentation\NotificationPageController::class, 'logs']);
        $web->add('GET', '/notifications/templates', [\WorkEddy\Modules\Notification\Presentation\NotificationPageController::class, 'templates']);
        $web->add('GET', '/notifications/settings', [\WorkEddy\Modules\Notification\Presentation\NotificationPageController::class, 'settings']);
    }, ['auth']);

    // API Routes
    $routes->group('/api/v1', function (RouteRegistrar $api) use ($uuidPattern) {
        // Logs
        $api->add('GET', '/notification/logs', [NotificationApiController::class, 'listLogs'], ['auth']);
        $api->add('GET', '/notification/logs/{id:' . $uuidPattern . '}', [NotificationApiController::class, 'showLog'], ['auth']);
        $api->add('POST', '/notification/logs/{id:' . $uuidPattern . '}/retry', [NotificationApiController::class, 'retryLog'], ['auth']);

        // Templates
        $api->add('GET', '/notification/templates', [NotificationApiController::class, 'listTemplates'], ['auth']);
        $api->add('GET', '/notification/templates/{id}/preview', [NotificationApiController::class, 'previewTemplate'], ['auth']);

        // Settings
        $api->add('GET', '/notification/settings', [NotificationApiController::class, 'getSettings'], ['auth']);
        $api->add('PUT', '/notification/settings', [NotificationApiController::class, 'updateSettings'], ['auth']);

        // Recipient preferences and inbox
        $api->add('GET', '/notification/preferences/me', [NotificationApiController::class, 'getMyPreferences'], ['auth']);
        $api->add('PUT', '/notification/preferences/me', [NotificationApiController::class, 'updateMyPreferences'], ['auth']);
        $api->add('GET', '/notification/inbox', [NotificationApiController::class, 'listInbox'], ['auth']);
        $api->add('POST', '/notification/inbox/{id:' . $uuidPattern . '}/read', [NotificationApiController::class, 'markInboxRead'], ['auth']);
    });
};
