<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http;

use FastRoute\RouteCollector;

final class RouteRegistrar
{
    /**
     * @var list<string>
     */
    private array $prefixes = [];

    /**
     * @var list<list<string>>
     */
    private array $middlewareStack = [];

    /**
     * @var list<string|null>
     */
    private array $moduleStack = [];

    /**
     * @var list<array{method: string, path: string, handler: callable|string|array, middleware: list<string>}>
     */
    private array $routes = [];

    public function __construct(private readonly ?RouteCollector $collector = null) {}

    /**
     * @param callable|string|array{class-string, string} $handler
     * @param list<string> $middleware
     */
    public function add(string $method, string $path, callable|string|array $handler, array $middleware = []): void
    {
        $groupMiddleware = [];
        foreach ($this->middlewareStack as $stack) {
            $groupMiddleware = array_merge($groupMiddleware, $stack);
        }

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->prefix() . $path,
            'handler' => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware),
            'module' => $this->moduleName(),
        ];

        $this->collector?->addRoute(strtoupper($method), $this->prefix() . $path, [
            'handler' => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware),
            'module' => $this->moduleName(),
        ]);
    }

    /**
     * @param callable(self): void $register
     * @param list<string> $middleware
     */
    public function group(string $prefix, callable $register, array $middleware = []): void
    {
        $this->prefixes[] = rtrim($prefix, '/');
        $this->middlewareStack[] = $middleware;

        try {
            $register($this);
        } finally {
            array_pop($this->prefixes);
            array_pop($this->middlewareStack);
        }
    }

    /**
     * @param callable(self): void $register
     */
    public function module(string $name, callable $register): void
    {
        $this->moduleStack[] = $name;

        try {
            $register($this);
        } finally {
            array_pop($this->moduleStack);
        }
    }

    /**
     * @return list<array{method: string, path: string, handler: callable|string|array, middleware: list<string>, module: string|null}>
     */
    public function routes(): array
    {
        return $this->routes;
    }

    private function prefix(): string
    {
        return implode('', $this->prefixes);
    }

    private function moduleName(): ?string
    {
        return $this->moduleStack === [] ? null : $this->moduleStack[array_key_last($this->moduleStack)];
    }
}
