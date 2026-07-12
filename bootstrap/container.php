<?php

declare(strict_types=1);

use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Config\RuntimeEnvironmentValidator;
use WorkEddy\Platform\Http\ExceptionHandler;
use WorkEddy\Platform\Http\HttpKernel;
use WorkEddy\Platform\Http\Middleware\PermissionMiddleware;
use WorkEddy\Platform\Http\RouteRegistrar;
use WorkEddy\Platform\Http\SystemController;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Module\ModuleRegistry;
use WorkEddy\Platform\Settings\SettingsRegistry;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Platform\Settings\SettingsStoreContract;
use WorkEddy\Platform\Http\Middleware\AuthGuardMiddleware;
use WorkEddy\Platform\Http\Middleware\ApiMiddleware;
use WorkEddy\Platform\Http\Middleware\CorsMiddleware;
use WorkEddy\Platform\Http\Middleware\CsrfMiddleware;
use WorkEddy\Platform\Http\Middleware\SecurityHeadersMiddleware;
use WorkEddy\Modules\IAM\Infrastructure\AuthRateLimitMiddleware;
use WorkEddy\Shared\Presentation\ViewRenderer;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

$moduleRegistry = new ModuleRegistry(require __DIR__ . '/modules.php');

$definitions = $moduleRegistry->definitions();

$definitions[ModuleRegistry::class] = $moduleRegistry;
$definitions[SettingsRegistry::class] = static fn(ContainerInterface $container): SettingsRegistry => SettingsRegistry::fromProviders(
    $container->get(ModuleRegistry::class)->settingsProviders(),
);
$definitions[SettingsService::class] = static fn(ContainerInterface $container): SettingsService => new SettingsService(
    registry: $container->get(SettingsRegistry::class),
    store: $container->get(SettingsStoreContract::class),
);
$definitions[LoggerInterface::class] = static fn(ContainerInterface $container): LoggerInterface => $container
    ->get(ILoggerFactory::class)
    ->channel((string) $container->get(ConfigLoader::class)->get('logging.default_channel', 'app'));
$definitions[RuntimeEnvironmentValidator::class] = static fn(ContainerInterface $container): RuntimeEnvironmentValidator => new RuntimeEnvironmentValidator(
    $container->get(ConfigLoader::class),
    dirname(__DIR__),
);
$definitions[ExceptionHandler::class] = static fn(ContainerInterface $container): ExceptionHandler => new ExceptionHandler(
    debug: (bool) $container->get(ConfigLoader::class)->get('app.debug', false),
    views: $container->get(ViewRenderer::class),
    logger: $container->get(LoggerInterface::class),
);
$definitions[SystemController::class] = static fn(ContainerInterface $container): SystemController => new SystemController(
    $container->get(RuntimeEnvironmentValidator::class),
);
$definitions[RouteRegistrar::class] = static function (ContainerInterface $container) use ($moduleRegistry): RouteRegistrar {
    $routes = new RouteRegistrar();
    (require __DIR__ . '/routes.php')($routes, $moduleRegistry, $container);

    return $routes;
};
$definitions[HttpKernel::class] = static function (ContainerInterface $container): HttpKernel {
    $kernel = new HttpKernel(
        container: $container,
        modules: $container->get(ModuleRegistry::class),
        dispatcher: (require __DIR__ . '/router.php')($container->get(ModuleRegistry::class), $container),
    );

    $kernel->registerMiddlewareAliases([
        'auth' => AuthGuardMiddleware::class,
        'iam_auth_rate_limit' => AuthRateLimitMiddleware::class,
        'permission' => PermissionMiddleware::class,
        'csrf' => CsrfMiddleware::class,
    ]);

    $kernel->addGlobalMiddleware(
        $container->get(CorsMiddleware::class),
        $container->get(SecurityHeadersMiddleware::class)
    );

    return $kernel;
};

return $definitions;
