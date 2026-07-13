<?php

declare(strict_types=1);

use WorkEddy\Modules\Task\Presentation\TaskController;
use WorkEddy\Modules\Task\Presentation\TaskPageController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $uuid = '[0-9a-fA-F-]{36}';

    $routes->group('', function (RouteRegistrar $web) use ($uuid): void {
        $web->add('GET', '/tasks', [TaskPageController::class, 'index'], ['auth']);
        $web->add('GET', '/tasks/{taskId:' . $uuid . '}', [TaskPageController::class, 'show'], ['auth']);
        $web->add('GET', '/tasks/{taskId:' . $uuid . '}/edit', [TaskPageController::class, 'edit'], ['auth']);
    });

    $routes->group('/api/v1', function (RouteRegistrar $api) use ($uuid): void {
        $api->add('GET', '/organizations/{id:' . $uuid . '}/tasks', [TaskController::class, 'list'], ['auth']);
        $api->add('POST', '/organizations/{id:' . $uuid . '}/tasks', [TaskController::class, 'create'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/tasks/{taskId:' . $uuid . '}', [TaskController::class, 'show'], ['auth']);
        $api->add('GET', '/tasks/{taskId:' . $uuid . '}', [TaskController::class, 'show'], ['auth']);
        $api->add('PUT', '/organizations/{id:' . $uuid . '}/tasks/{taskId:' . $uuid . '}', [TaskController::class, 'update'], ['auth']);
        $api->add('PUT', '/tasks/{taskId:' . $uuid . '}', [TaskController::class, 'update'], ['auth']);
        $api->add('DELETE', '/organizations/{id:' . $uuid . '}/tasks/{taskId:' . $uuid . '}', [TaskController::class, 'delete'], ['auth']);
        $api->add('DELETE', '/tasks/{taskId:' . $uuid . '}', [TaskController::class, 'delete'], ['auth']);
    });
};
