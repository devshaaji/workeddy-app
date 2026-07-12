<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http;

use FastRoute\Dispatcher;
use WorkEddy\Platform\Authorization\IAuthorizationService;
use WorkEddy\Platform\Http\Middleware\PermissionMiddleware;
use WorkEddy\Platform\Module\ModuleRegistry;
use WorkEddy\Shared\Exceptions\MethodNotAllowedException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use Psr\Container\ContainerInterface;

final class HttpKernel
{
    /** @var list<IMiddleware> */
    private array $globalMiddleware = [];

    /**
     * @var array<string, class-string<IMiddleware>>
     */
    private array $middlewareAliases = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ModuleRegistry $modules,
        private readonly Dispatcher|RouteRegistrar $dispatcher,
    ) {}

    public function addGlobalMiddleware(IMiddleware ...$middleware): void
    {
        foreach ($middleware as $item) {
            $this->globalMiddleware[] = $item;
        }
    }

    /**
     * @param array<string, class-string<IMiddleware>> $aliases
     */
    public function registerMiddlewareAliases(array $aliases): void
    {
        $this->middlewareAliases = array_replace($this->middlewareAliases, $aliases);
    }

    /**
     * @return array<string, class-string>
     */
    public function middlewareAliases(): array
    {
        return $this->middlewareAliases;
    }

    public function handle(Request $request): Response
    {
        if ($this->dispatcher instanceof Dispatcher) {
            return $this->handleDispatchedRoute($request);
        }

        return $this->handleRegisteredRoutes($request);
    }

    private function handleDispatchedRoute(Request $request): Response
    {
        $route = $this->dispatcher->dispatch($request->method, $request->uri);

        if ($route[0] === Dispatcher::NOT_FOUND) {
            throw new NotFoundException('Route not found');
        }

        if ($route[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            throw new MethodNotAllowedException('Method not allowed');
        }

        $routeInfo = $route[1];
        $params = $route[2] ?? [];
        if (is_array($routeInfo) && isset($routeInfo['module']) && is_string($routeInfo['module'])) {
            $this->modules->bootModule($routeInfo['module'], $this->container);
        }

        return $this->dispatch($request->withRouteParams($params), [
            'method' => $request->method,
            'path' => $request->uri,
            'handler' => is_array($routeInfo) && array_key_exists('handler', $routeInfo) ? $routeInfo['handler'] : $routeInfo,
            'middleware' => is_array($routeInfo) && array_key_exists('middleware', $routeInfo) ? $routeInfo['middleware'] : [],
            'module' => is_array($routeInfo) && array_key_exists('module', $routeInfo) ? $routeInfo['module'] : null,
        ]);
    }

    private function handleRegisteredRoutes(Request $request): Response
    {
        $matchesForMethod = [];
        foreach ($this->dispatcher->routes() as $route) {
            $params = $this->matchPath($route['path'], $request->uri);
            if ($params === null) {
                continue;
            }

            if ($route['method'] !== strtoupper($request->method)) {
                $matchesForMethod[] = $route['method'];
                continue;
            }

            if (isset($route['module']) && is_string($route['module'])) {
                $this->modules->bootModule($route['module'], $this->container);
            }

            return $this->dispatch($request->withRouteParams($params), $route);
        }

        if ($matchesForMethod !== []) {
            throw new MethodNotAllowedException('Method not allowed');
        }

        throw new NotFoundException('Route not found');
    }

    /**
     * @param array{method: string, path: string, handler: callable|string|array, middleware: list<string>, module?: string|null} $route
     */
    private function dispatch(Request $request, array $route): Response
    {
        $handler = $route['handler'];
        $controllerHandler = function (Request $req) use ($handler): Response {
            $result = is_callable($handler)
                ? $handler($req)
                : $this->resolveController($handler, $req);

            return $this->normalize($result);
        };

        $pipeline = new MiddlewarePipeline();
        foreach ($this->globalMiddleware as $middleware) {
            $pipeline->pipe($middleware);
        }

        foreach ($route['middleware'] as $alias) {
            $pipeline->pipe($this->resolveMiddleware($alias));
        }

        return $pipeline->handle($request, $controllerHandler);
    }

    /**
     * @return array<string, string>|null
     */
    private function matchPath(string $routePath, string $requestPath): ?array
    {
        $routeParts = array_values(array_filter(explode('/', trim($routePath, '/')), static fn(string $part): bool => $part !== ''));
        $requestParts = array_values(array_filter(explode('/', trim($requestPath, '/')), static fn(string $part): bool => $part !== ''));

        if ($routeParts === [] && $requestParts === []) {
            return [];
        }

        if (count($routeParts) !== count($requestParts)) {
            return null;
        }

        $params = [];
        foreach ($routeParts as $index => $part) {
            $requestPart = $requestParts[$index];
            if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                $params[trim($part, '{}')] = rawurldecode($requestPart);
                continue;
            }

            if ($part !== $requestPart) {
                return null;
            }
        }

        return $params;
    }

    private function resolveController(mixed $handler, Request $request): mixed
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = $this->container->has($class) ? $this->container->get($class) : new $class();

            // $reflection = new \ReflectionMethod($controller, $method);
            // if ($reflection->getNumberOfParameters() >= 2) {
            //     return $controller->{$method}($request, $request->routeParams);
            // }

            return $controller->{$method}($request);
        }

        return ['success' => true, 'data' => ['handler' => $handler]];
    }

    private function resolveMiddleware(string $alias): IMiddleware
    {
        $parameter = null;
        $baseAlias = $alias;
        if (str_contains($alias, ':')) {
            [$baseAlias, $parameter] = explode(':', $alias, 2);
        }

        $class = $this->middlewareAliases[$baseAlias] ?? null;
        if ($class === null) {
            throw new \RuntimeException("Unknown route middleware alias: {$baseAlias}");
        }

        if ($parameter !== null && $class === PermissionMiddleware::class) {
            return new PermissionMiddleware($this->container->get(IAuthorizationService::class), $parameter);
        }

        if ($this->container->has($class)) {
            $middleware = $this->container->get($class);
            if ($middleware instanceof IMiddleware) {
                return $middleware;
            }
        }

        throw new \RuntimeException("Route middleware alias did not resolve to middleware: {$alias}");
    }

    private function normalize(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        if ($result === null) {
            return Response::json(['success' => true, 'data' => null]);
        }

        return Response::html((string) $result);
    }
}
