<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use WorkEddy\Modules\Content\Application\Services\ContentQueryService;
use WorkEddy\Modules\Content\Application\Services\ContentWorkflowService;
use WorkEddy\Modules\Content\Authorization\ContentPermissionDefinitionProvider;
use WorkEddy\Modules\Content\Domain\Contracts\ContentPageReader;
use WorkEddy\Modules\Content\Domain\Contracts\ContentPreviewReader;
use WorkEddy\Modules\Content\Domain\Contracts\IContentMediaRepository;
use WorkEddy\Modules\Content\Domain\Contracts\IContentPageRepository;
use WorkEddy\Modules\Content\Infrastructure\ContentMediaRepository;
use WorkEddy\Modules\Content\Infrastructure\ContentPageRepository;
use WorkEddy\Modules\Content\Support\ContentPageSchemaRegistry;
use WorkEddy\Modules\Content\Support\MethodologyPageSchema;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'content';
    }

    public function getDefinitions(): array
    {
        return [
            IContentPageRepository::class => static fn(ContainerInterface $c): IContentPageRepository => new ContentPageRepository($c->get(Connection::class)),
            IContentMediaRepository::class => static fn(ContainerInterface $c): IContentMediaRepository => new ContentMediaRepository($c->get(Connection::class)),
            ContentPageSchemaRegistry::class => static fn(): ContentPageSchemaRegistry => new ContentPageSchemaRegistry([
                new MethodologyPageSchema(),
            ]),
            ContentWorkflowService::class => static fn(ContainerInterface $c): ContentWorkflowService => new ContentWorkflowService(
                $c->get(IContentPageRepository::class),
                $c->get(ContentPageSchemaRegistry::class),
                $c->get(\WorkEddy\Platform\Audit\IAuditService::class),
                $c->get(IContentMediaRepository::class),
            ),
            ContentQueryService::class => static fn(ContainerInterface $c): ContentQueryService => new ContentQueryService($c->get(IContentPageRepository::class)),
            ContentPageReader::class => static fn(ContainerInterface $c): ContentPageReader => $c->get(ContentQueryService::class),
            ContentPreviewReader::class => static fn(ContainerInterface $c): ContentPreviewReader => $c->get(ContentQueryService::class),
            \WorkEddy\Modules\Content\Presentation\ContentPageController::class => \DI\autowire(),
            \WorkEddy\Modules\Content\Presentation\ContentApiController::class => \DI\autowire(),
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
        return new ContentPermissionDefinitionProvider();
    }

    public function getSettingsProvider(): mixed
    {
        return null;
    }

    public function getConsoleCommandProvider(): mixed
    {
        return null;
    }

    public function boot(ContainerInterface $container): void
    {
        unset($container);
    }
}
