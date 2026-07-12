<?php

declare(strict_types=1);

use WorkEddy\Modules\Ergonomics\Presentation\ErgonomicsController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $routes->group('/api/v1/ergonomics', function (RouteRegistrar $api): void {
        $api->add('GET', '/models', [ErgonomicsController::class, 'models'], ['auth']);
        $api->add('POST', '/score', [ErgonomicsController::class, 'score'], ['auth']);
    });
};
