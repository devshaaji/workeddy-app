<?php

declare(strict_types=1);

use WorkEddy\Modules\Storage\Presentation\StorageApiController;
use WorkEddy\Modules\Storage\Presentation\StoragePageController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $uuidPattern = '[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[1-5][0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}';

    $routes->group('', function (RouteRegistrar $web): void {
        $web->add('GET', '/storage', [StoragePageController::class, 'adminIndex'], ['permission:storage.file.view']);
    }, ['auth']);

    $routes->group('/api/v1/files', function (RouteRegistrar $public) use ($uuidPattern): void {
        $public->add('GET', '/{uuid:' . $uuidPattern . '}/view', [StorageApiController::class, 'publicView']);
        $public->add('GET', '/{uuid:' . $uuidPattern . '}/download', [StorageApiController::class, 'publicDownload']);
    });

    $routes->group('/api/v1/storage', function (RouteRegistrar $api) use ($uuidPattern): void {
        $api->add('GET', '/summary', [StorageApiController::class, 'summary'], ['permission:storage.file.view']);
        $api->add('GET', '/files', [StorageApiController::class, 'list'], ['permission:storage.file.view']);
        $api->add('POST', '/files', [StorageApiController::class, 'upload'], ['permission:storage.file.upload']);
        $api->add('GET', '/files/{uuid:' . $uuidPattern . '}', [StorageApiController::class, 'show'], ['permission:storage.file.view']);
        $api->add('GET', '/files/{uuid:' . $uuidPattern . '}/view', [StorageApiController::class, 'view'], ['permission:storage.file.view']);
        $api->add('GET', '/files/{uuid:' . $uuidPattern . '}/download', [StorageApiController::class, 'download'], ['permission:storage.file.download']);
        $api->add('GET', '/files/{uuid:' . $uuidPattern . '}/usage', [StorageApiController::class, 'usage'], ['permission:storage.file.view']);
        $api->add('POST', '/files/{uuid:' . $uuidPattern . '}/restore', [StorageApiController::class, 'restore'], ['permission:storage.file.delete']);
        // Soft delete (move to trash) — preserves the original endpoint behaviour.
        $api->add('DELETE', '/files/{uuid:' . $uuidPattern . '}', [StorageApiController::class, 'delete'], ['permission:storage.file.delete']);
        // Permanent delete — blocked automatically if the file is still referenced elsewhere.
        $api->add('DELETE', '/files/{uuid:' . $uuidPattern . '}/permanent', [StorageApiController::class, 'destroy'], ['permission:storage.file.delete']);
        $api->add('GET', '/settings', [StorageApiController::class, 'settings'], ['permission:storage.settings.manage']);
        $api->add('PUT', '/settings', [StorageApiController::class, 'updateSettings'], ['permission:storage.settings.manage']);
    }, ['auth']);
};
