<?php

declare(strict_types=1);

use WorkEddy\Platform\Http\RouteRegistrar;
use WorkEddy\Platform\Module\ModuleRegistry;
use Psr\Container\ContainerInterface;
use function FastRoute\cachedDispatcher;
use function FastRoute\simpleDispatcher;

return static function (ModuleRegistry $modules, ContainerInterface $container): FastRoute\Dispatcher {
    $envBool = static function (string $key, bool $default): bool {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    };

    $production = strtolower((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production')) === 'production';

    $routeFiles = [];
    $registerRoutes = static function (FastRoute\RouteCollector $routes) use ($modules, $container, &$routeFiles): void {
        $registrar = new RouteRegistrar($routes);
        (require __DIR__ . '/routes.php')($registrar, $modules, $container, static function (string $routeFile) use (&$routeFiles): void {
            $routeFiles[$routeFile] = true;
        });
    };

    $cacheFile = dirname(__DIR__) . '/storage/cache/routes/dispatcher.php';
    $cacheEnabled = $envBool('WorkEddy_ROUTE_CACHE', $production);
    if (!$cacheEnabled) {
        return simpleDispatcher($registerRoutes);
    }

    $metadataFile = $cacheFile . '.meta.json';
    simpleDispatcher($registerRoutes);
    $fingerprint = hash('sha256', json_encode(array_map(
        static fn(string $routeFile): array => [
            'path' => $routeFile,
            'mtime' => is_file($routeFile) ? (filemtime($routeFile) ?: 0) : 0,
        ],
        array_keys($routeFiles),
    ), JSON_THROW_ON_ERROR));

    if (is_file($cacheFile)) {
        $metadata = is_file($metadataFile) ? json_decode((string) file_get_contents($metadataFile), true) : null;
        if (!is_array($metadata) || ($metadata['fingerprint'] ?? null) !== $fingerprint) {
            @unlink($cacheFile);
        }
    }

    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }

    $dispatcher = cachedDispatcher($registerRoutes, [
        'cacheFile' => $cacheFile,
    ]);

    file_put_contents($metadataFile, json_encode([
        'fingerprint' => $fingerprint,
        'generated_at' => gmdate('c'),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

    return $dispatcher;
};
