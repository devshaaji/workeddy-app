<?php

declare(strict_types=1);

namespace WorkEddy\Tests\IAM;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use WorkEddy\Platform\Http\RouteRegistrar;
use WorkEddy\Platform\Module\ModuleRegistry;

final class RouteAuthorizationCoverageTest extends TestCase
{
    public function test_non_public_routes_declare_auth_or_permission_middleware(): void
    {
        $registry = new ModuleRegistry(require dirname(__DIR__, 2) . '/bootstrap/modules.php');
        $routes = new RouteRegistrar();

        (require dirname(__DIR__, 2) . '/bootstrap/routes.php')(
            $routes,
            $registry,
            new NullRouteContainer(),
        );

        $unprotected = [];
        foreach ($routes->routes() as $route) {
            if ($this->isPublicRoute($route['method'], $route['path'])) {
                continue;
            }

            if ($this->hasAuthorizationMiddleware($route['middleware'])) {
                continue;
            }

            $unprotected[] = sprintf('%s %s', $route['method'], $route['path']);
        }

        self::assertSame([], $unprotected, 'Non-public routes must declare auth or permission middleware.');
    }

    private function hasAuthorizationMiddleware(array $middleware): bool
    {
        foreach ($middleware as $alias) {
            if ($alias === 'auth' || str_starts_with($alias, 'permission:')) {
                return true;
            }
        }

        return false;
    }

    private function isPublicRoute(string $method, string $path): bool
    {
        $route = $method . ' ' . $path;

        if (in_array($route, [
            'GET /health',
            'GET /ready',
            'GET /',
            'GET /about-us',
            'GET /founder-message',
            'GET /why-us',
            'GET /contact-us',
            'POST /contact-us/submit',
            'GET /plans',
            'GET /privacy-policy',
            'GET /terms-of-service',
            'GET /login',
            'GET /register',
            'GET /forgot-password',
            'GET /reset-password',
            'GET /verify-otp',
            'GET /verify-email',
            'GET /auth/login',
            'POST /api/v1/auth/register',
            'POST /api/v1/auth/login',
            'POST /api/v1/auth/forgot-password',
            'POST /api/v1/auth/reset-password',
            'POST /api/v1/auth/resend-otp',
            'POST /api/v1/auth/verify-otp',
            'POST /api/v1/payment/webhook',
            'POST /api/v1/payment/webhook/{gateway}',
            'POST /api/v1/admin/payment/webhook',
            'POST /api/v1/admin/payment/webhook/{gateway}',
            'POST /transport/inbox',
            'GET /api/v1/transport/capabilities',
            'POST /api/v1/transport/inbound',
            'POST /api/v1/transport/inbound/batch',
        ], true)) {
            return true;
        }

        return preg_match('#^/api/v1/(reporting/signed-access/\{token:|files/\{uuid:|research-exports/signed-access/\{token:)#', $path) === 1
            || preg_match('#^/api/v1/privacy/signed-video-access/\{token\}#', $path) === 1
            || preg_match('#^POST /api/v1/internal/assessment-video/jobs/#', $route) === 1;
    }
}

final class NullRouteContainer implements ContainerInterface
{
    public function get(string $id): mixed
    {
        throw new \RuntimeException('Container access is not expected during route registration: ' . $id);
    }

    public function has(string $id): bool
    {
        return false;
    }
}
