<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Storage;

use WorkEddy\Modules\Storage\Authorization\StoragePermissionDefinitionProvider;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageRepository;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Modules\Storage\Infrastructure\StorageRepository;
use WorkEddy\Modules\Storage\Infrastructure\StorageService;
use WorkEddy\Modules\Storage\Presentation\StorageApiController;
use WorkEddy\Modules\Storage\Presentation\StoragePageController;
use WorkEddy\Modules\Storage\Settings\StorageSettings;
use WorkEddy\Modules\Storage\Settings\StorageSettingsProvider;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Settings\SettingsRegistry;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Presentation\ViewRenderer;
use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'Storage';
    }

    public function getDefinitions(): array
    {
        return [
            StorageSettingsProvider::class => fn() => new StorageSettingsProvider(),
            StorageSettings::class => fn(SettingsService $ss) => new StorageSettings($ss),
            IStorageRepository::class => fn(Connection $c) => new StorageRepository($c),
            StorageRepository::class => fn(ContainerInterface $c) => $c->get(IStorageRepository::class),
            IStorageService::class => fn(ContainerInterface $c) => new StorageService(
                $c->get(IStorageRepository::class),
                $c->get(StorageSettings::class),
                $c->get(IAuditService::class),
            ),
            StorageService::class => fn(ContainerInterface $c) => $c->get(IStorageService::class),
            StorageApiController::class => fn(ContainerInterface $c) => new StorageApiController(
                $c->get(IStorageService::class),
                $c->get(ISessionService::class),
                $c->get(StorageSettings::class),
                $c->get(SettingsService::class),
                $c->get(IAuditService::class),
            ),
            StoragePageController::class => fn(ViewRenderer $v, ISessionService $s) => new StoragePageController($v, $s),
        ];
    }

    public function getRouteFile(): ?string
    {
        return __DIR__ . '/Presentation/routes.php';
    }

    public function getEventListeners(): array
    {
        return [];
    }

    public function getJobHandlers(): array
    {
        return [];
    }

    public function getPermissionDefinitionProvider(): ?IPermissionDefinitionProvider
    {
        return new StoragePermissionDefinitionProvider();
    }

    public function getSettingsProvider(): mixed
    {
        return new StorageSettingsProvider();
    }

    public function getConsoleCommandProvider(): mixed
    {
        return null;
    }

    public function boot(ContainerInterface $container): void {}
}
