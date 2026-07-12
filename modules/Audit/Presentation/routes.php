<?php

/** Audit module route registrations. */

declare(strict_types=1);

use WorkEddy\Platform\Http\RouteRegistrar;
use WorkEddy\Modules\Audit\Presentation\AuditController;
use WorkEddy\Modules\Audit\Presentation\AuditPageController;

return function (RouteRegistrar $routes): void {
    $uuidPattern = '[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[1-5][0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}';
    $registerLogPages = static function (RouteRegistrar $page) use ($uuidPattern): void {
        $page->add('GET', '/logs', [AuditPageController::class, 'logs'], ['auth']);
        $page->add('GET', '/logs/{id:' . $uuidPattern . '}', [AuditPageController::class, 'showLog'], ['auth']);
        $page->add('GET', '/export', [AuditPageController::class, 'export'], ['auth']);
        $page->add('GET', '/settings', [AuditPageController::class, 'settings'], ['auth']);
    };
    $routes->group('/audit', $registerLogPages);
    $routes->group('', static function (RouteRegistrar $page) use ($uuidPattern): void {
        $page->add('GET', '/logs', [AuditPageController::class, 'logs'], ['auth']);
        $page->add('GET', '/logs/{id:' . $uuidPattern . '}', [AuditPageController::class, 'showLog'], ['auth']);
    });

    $registerLogApi = static function (RouteRegistrar $api) use ($uuidPattern): void {
        $api->add('GET', '/logs', [AuditController::class, 'list'], ['auth']);
        $api->add('GET', '/logs/{id:' . $uuidPattern . '}', [AuditController::class, 'show'], ['auth']);
        $api->add('GET', '/logs/export', [AuditController::class, 'export'], ['auth']);
        $api->add('GET', '/settings', [AuditController::class, 'settings'], ['auth']);
        $api->add('PUT', '/settings', [AuditController::class, 'updateSettings'], ['auth']);
    };
    $routes->group('/api/v1/audit', $registerLogApi);
    $routes->group('/api/v1', static function (RouteRegistrar $api) use ($uuidPattern): void {
        $api->add('GET', '/logs', [AuditController::class, 'list'], ['auth']);
        $api->add('GET', '/logs/{id:' . $uuidPattern . '}', [AuditController::class, 'show'], ['auth']);
        $api->add('GET', '/logs/export', [AuditController::class, 'export'], ['auth']);
    });
};
