<?php

/**
 * Audit module service provider.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\Audit;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Audit\Authorization\AuditPermissionDefinitionProvider;
use WorkEddy\Modules\Audit\Domain\Contracts\IAuditLogRepository;
use WorkEddy\Modules\Audit\Infrastructure\AuditLogRepository;
use WorkEddy\Modules\Audit\Settings\AuditSettingsProvider;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use Psr\Container\ContainerInterface;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'audit';
    }

    public function getDefinitions(): array
    {
        return [
            \WorkEddy\Platform\Audit\AppendOnlyAuditLogContract::class => static fn(ContainerInterface $c): \WorkEddy\Platform\Audit\AppendOnlyAuditLogContract => $c->has('db')
                ? new \WorkEddy\Platform\Audit\DbalAuditLog($c->get('db'))
                : new \WorkEddy\Platform\Audit\InMemoryAuditLog(),
            \WorkEddy\Platform\Audit\IAuditService::class => static fn(ContainerInterface $c): \WorkEddy\Platform\Audit\IAuditService => new \WorkEddy\Modules\Audit\Infrastructure\SettingsAwareAuditService(
                new \WorkEddy\Platform\Audit\AppendOnlyAuditService(
                    $c->get(\WorkEddy\Platform\Audit\AppendOnlyAuditLogContract::class)
                ),
                $c->get(\WorkEddy\Modules\Audit\Settings\AuditSettings::class)
            ),
            AuditLogRepository::class => static fn(ContainerInterface $c): AuditLogRepository => new AuditLogRepository($c->get(Connection::class)),
            IAuditLogRepository::class => static fn(ContainerInterface $c): IAuditLogRepository => $c->get(AuditLogRepository::class),
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
        return new AuditPermissionDefinitionProvider();
    }

    public function getSettingsProvider(): ?IModuleSettingsProvider
    {
        return new AuditSettingsProvider();
    }

    public function getConsoleCommandProvider(): mixed
    {
        return null;
    }

    public function boot(ContainerInterface $container): void {}
}
