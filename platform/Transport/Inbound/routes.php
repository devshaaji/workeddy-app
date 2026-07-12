<?php

declare(strict_types=1);

use WorkEddy\Platform\Http\RouteRegistrar;
use WorkEddy\Platform\Transport\Inbound\Http\TransportInboxController;
use WorkEddy\Platform\Transport\Shared\Http\TransportCapabilitiesController;

return static function (RouteRegistrar $routes): void {
    $routes->add('POST', '/transport/inbox', [TransportInboxController::class, 'receive']);
    $routes->add('GET', '/api/v1/transport/capabilities', [TransportCapabilitiesController::class, 'show']);
    $routes->add('POST', '/api/v1/transport/inbound', [TransportInboxController::class, 'receive']);
    $routes->add('POST', '/api/v1/transport/inbound/batch', [TransportInboxController::class, 'receiveBatch']);
};
