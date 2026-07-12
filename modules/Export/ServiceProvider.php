<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use WorkEddy\Modules\Export\Application\IssueSignedResearchExportAccessUseCase;
use WorkEddy\Modules\Export\Application\ReadSignedResearchExportAccessUseCase;
use WorkEddy\Modules\Export\Application\Services\ResearchExportDeidentificationService;
use WorkEddy\Modules\Export\Application\Services\ResearchExportFileWriter;
use WorkEddy\Modules\Export\Application\Support\ResearchExportColumnCatalog;
use WorkEddy\Modules\Export\Application\UseCases\GenerateResearchExportUseCase;
use WorkEddy\Modules\Export\Application\UseCases\PreviewResearchExportUseCase;
use WorkEddy\Modules\Export\Authorization\ExportPermissionDefinitionProvider;
use WorkEddy\Modules\Export\Domain\Contracts\IResearchExportRepository;
use WorkEddy\Modules\Export\Infrastructure\ResearchExportRepository;
use WorkEddy\Modules\Export\Presentation\ExportApiController;
use WorkEddy\Modules\Export\Presentation\ExportPageController;
use WorkEddy\Modules\Export\Presentation\ExportPageData;
use WorkEddy\Modules\Export\Settings\ExportSettings;
use WorkEddy\Modules\Export\Settings\ExportSettingsProvider;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'export';
    }

    public function getDefinitions(): array
    {
        return [
            ExportSettings::class => static fn(ContainerInterface $c): ExportSettings => new ExportSettings($c->get(SettingsService::class)),
            IResearchExportRepository::class => static fn(ContainerInterface $c): IResearchExportRepository => new ResearchExportRepository($c->get(Connection::class)),
            ResearchExportColumnCatalog::class => \DI\autowire(),
            ResearchExportDeidentificationService::class => \DI\autowire(),
            ResearchExportFileWriter::class => \DI\autowire(),
            PreviewResearchExportUseCase::class => \DI\autowire(),
            GenerateResearchExportUseCase::class => \DI\autowire(),
            IssueSignedResearchExportAccessUseCase::class => static fn(ContainerInterface $c): IssueSignedResearchExportAccessUseCase => new IssueSignedResearchExportAccessUseCase(
                $c->get(IResearchExportRepository::class),
                $c->get(ExportSettings::class),
                $c->get(IAuditService::class),
                $c->get(IClock::class),
                $c->get(IPermissionService::class),
            ),
            ReadSignedResearchExportAccessUseCase::class => static fn(ContainerInterface $c): ReadSignedResearchExportAccessUseCase => new ReadSignedResearchExportAccessUseCase(
                $c->get(IResearchExportRepository::class),
                $c->get(IStorageService::class),
                $c->get(IClock::class),
                $c->get(IAuditService::class),
            ),
            ExportPageData::class => \DI\autowire(),
            ExportApiController::class => \DI\autowire(),
            ExportPageController::class => static fn(ContainerInterface $c): ExportPageController => new ExportPageController(
                $c->get(ISessionService::class),
                $c->get(IPermissionService::class),
                $c->get(ViewRenderer::class),
                $c->get(ExportPageData::class),
            ),
        ];
    }

    public function getRouteFile(): ?string
    {
        return __DIR__ . '/Presentation/routes.php';
    }

    public function getEventListeners(): array { return []; }
    public function getJobHandlers(): array { return []; }
    public function getPermissionDefinitionProvider(): ?IPermissionDefinitionProvider { return new ExportPermissionDefinitionProvider(); }
    public function getSettingsProvider(): ?IModuleSettingsProvider { return new ExportSettingsProvider(); }
    public function getConsoleCommandProvider(): mixed { return null; }
    public function boot(ContainerInterface $container): void { unset($container); }
}
