<?php

declare(strict_types=1);

use WorkEddy\Modules\Export\Presentation\ExportApiController;
use WorkEddy\Modules\Export\Presentation\ExportPageController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $uuid = '[0-9a-fA-F-]{36}';
    $token = '[A-Za-z0-9\\-_\\.]+'; 

    $routes->group('', static function (RouteRegistrar $web): void {
        $web->add('GET', '/research-exports', [ExportPageController::class, 'index'], ['auth']);
    });

    $routes->group('/api/v1/research-exports', static function (RouteRegistrar $api) use ($uuid): void {
        $api->add('GET', '/preview', [ExportApiController::class, 'preview'], ['auth']);
        $api->add('POST', '', [ExportApiController::class, 'generate'], ['auth']);
        $api->add('GET', '', [ExportApiController::class, 'list'], ['auth']);
        $api->add('POST', '/{exportUuid:' . $uuid . '}/signed-access', [ExportApiController::class, 'issueSignedAccess'], ['auth']);
    });

    $routes->group('/api/v1/research-exports', static function (RouteRegistrar $public) use ($token): void {
        $public->add('GET', '/signed-access/{token:' . $token . '}', [ExportApiController::class, 'readSignedAccess']);
    });
};
