<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Website;

use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Website\Presentation\Controllers\PageController;
use WorkEddy\Modules\Website\Presentation\WebsitePageData;
use WorkEddy\Modules\Website\Settings\WebsiteSettings;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Shared\Presentation\ViewRenderer;
use Psr\Container\ContainerInterface;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'website';
    }

    public function getDefinitions(): array
    {
        return [
            WebsitePageData::class => static fn(ContainerInterface $c): WebsitePageData => new WebsitePageData(
                $c->get(WebsiteSettings::class),
                $c->get(ISubscriptionPlanRepository::class),
            ),
            PageController::class => static fn(ContainerInterface $c): PageController => new PageController(
                $c->get(ViewRenderer::class),
                $c->get(WebsitePageData::class),
                $c->get(NotificationServiceInterface::class),
            ),
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
        return null;
    }

    public function getSettingsProvider(): ?IModuleSettingsProvider
    {
        return new \WorkEddy\Modules\Website\Settings\WebsiteSettingsProvider();
    }

    public function getConsoleCommandProvider(): mixed
    {
        return null;
    }

    public function boot(ContainerInterface $container): void {}
}
