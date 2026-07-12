<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http;

final class FastRouteDispatcherFactory
{
    public function create(RouteRegistrar $routes): object
    {
        if (!function_exists('\FastRoute\simpleDispatcher')) {
            throw new \RuntimeException('FastRoute is not installed. Run composer install before using the production router.');
        }

        return \FastRoute\simpleDispatcher(static function ($collector) use ($routes): void {
            foreach ($routes->routes() as $route) {
                $collector->addRoute($route['method'], $route['path'], $route['handler']);
            }
        });
    }
}
