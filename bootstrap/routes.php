<?php

declare(strict_types=1);

use WorkEddy\Platform\Http\RouteRegistrar;
use WorkEddy\Platform\Module\ModuleRegistry;
use WorkEddy\Platform\Http\SystemController;
use WorkEddy\Shared\Presentation\CorePageController;
use Psr\Container\ContainerInterface;

return static function (RouteRegistrar $routes, ModuleRegistry $modules, ContainerInterface $container, ?callable $routeSource = null): void {
    unset($container);

    $routes->add('GET', '/health', [SystemController::class, 'health']);
    $routes->add('GET', '/ready', [SystemController::class, 'ready']);
    $routes->add('GET', '/dashboard', [CorePageController::class, 'dashboard'], ['auth']);

    foreach ($modules->providers() as $provider) {
        $routeFile = $provider->getRouteFile();
        if ($routeFile === null || !is_file($routeFile)) {
            continue;
        }

        $routes->module($provider->getName(), static function (RouteRegistrar $moduleRoutes) use ($routeFile, $routeSource): void {
            if ($routeSource !== null) {
                $routeSource($routeFile);
            }
            (require $routeFile)($moduleRoutes);
            if ($routeSource !== null) {
                $routeSource(__FILE__);
            }
        });
    }
};
