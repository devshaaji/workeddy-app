<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting;

use WorkEddy\Modules\Content\Domain\Contracts\ContentPageReader;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Reporting\Application\IssueSignedReportAccessUseCase;
use WorkEddy\Modules\Reporting\Application\RegenerateReportArtifactUseCase;
use WorkEddy\Modules\Reporting\Application\ReadSignedReportAccessUseCase;
use WorkEddy\Modules\Reporting\Application\Services\PlatformAggregateMetricsService;
use WorkEddy\Modules\Reporting\Application\Services\ReportArtifactService;
use WorkEddy\Modules\Reporting\Application\Services\ReportingSnapshotService;
use WorkEddy\Modules\Reporting\Application\UseCases\CreateNationalStatisticUseCase;
use WorkEddy\Modules\Reporting\Application\UseCases\DeleteNationalStatisticUseCase;
use WorkEddy\Modules\Reporting\Application\UseCases\UpdateNationalStatisticUseCase;
use WorkEddy\Modules\Reporting\Application\UseCases\GeneratePdf;
use WorkEddy\Modules\Reporting\Application\UseCases\GenerateCsv;
use WorkEddy\Modules\Reporting\Authorization\ReportingPermissionDefinitionProvider;
use WorkEddy\Modules\Reporting\Console\NationalMetricsRefreshCommand;
use WorkEddy\Modules\Reporting\Console\ReportingConsoleCommandProvider;
use WorkEddy\Modules\Reporting\Domain\Contracts\INationalStatisticRepository;
use WorkEddy\Modules\Reporting\Domain\Contracts\IPlatformAggregateMetricRepository;
use WorkEddy\Modules\Reporting\Domain\Contracts\IReportArtifactRepository;
use WorkEddy\Modules\Reporting\Infrastructure\NationalStatisticRepository;
use WorkEddy\Modules\Reporting\Infrastructure\PlatformAggregateMetricRepository;
use WorkEddy\Modules\Reporting\Infrastructure\ReportArtifactRepository;
use WorkEddy\Modules\Reporting\Presentation\NationalStatisticAdminController;
use WorkEddy\Modules\Reporting\Presentation\ReportingApiController;
use WorkEddy\Modules\Reporting\Presentation\ReportingPageData;
use WorkEddy\Modules\Reporting\Presentation\ReportingPageController;
use WorkEddy\Modules\Reporting\Settings\ReportingSettings;
use WorkEddy\Modules\Reporting\Settings\ReportingSettingsProvider;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Presentation\ViewRenderer;
use WorkEddy\Platform\Cache\ICacheService;
use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use WorkEddy\Platform\Console\Command\CommandLockRunner;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'Reporting';
    }

    public function getDefinitions(): array
    {
        return [
            ReportingSettingsProvider::class => static fn(): ReportingSettingsProvider => new ReportingSettingsProvider(),
            ReportingSettings::class => static fn(ContainerInterface $c): ReportingSettings => new ReportingSettings($c->get(SettingsService::class)),
            IReportArtifactRepository::class => static fn(ContainerInterface $c): IReportArtifactRepository => new ReportArtifactRepository($c->get(Connection::class)),
            ReportArtifactService::class => static fn(ContainerInterface $c): ReportArtifactService => new ReportArtifactService($c->get(IReportArtifactRepository::class)),
            IssueSignedReportAccessUseCase::class => static fn(ContainerInterface $c): IssueSignedReportAccessUseCase => new IssueSignedReportAccessUseCase(
                $c->get(IReportArtifactRepository::class),
                $c->get(ReportingSettings::class),
                $c->get(IAuditService::class),
                $c->get(IClock::class),
            ),
            ReadSignedReportAccessUseCase::class => static fn(ContainerInterface $c): ReadSignedReportAccessUseCase => new ReadSignedReportAccessUseCase(
                $c->get(IReportArtifactRepository::class),
                $c->get(IStorageService::class),
                $c->get(IClock::class),
                $c->get(IAuditService::class),
            ),
            RegenerateReportArtifactUseCase::class => static fn(ContainerInterface $c): RegenerateReportArtifactUseCase => new RegenerateReportArtifactUseCase(
                $c->get(IReportArtifactRepository::class),
                $c->get(GeneratePdf::class),
                $c->get(GenerateCsv::class),
                $c->get(IAuditService::class),
            ),
            ReportingSnapshotService::class => static fn(ContainerInterface $c): ReportingSnapshotService => new ReportingSnapshotService(
                $c->get(Connection::class),
                $c->get(ICacheService::class),
                $c->has(IAssessmentRepository::class) ? $c->get(IAssessmentRepository::class) : null,
                $c->has(ICorrectiveActionRepository::class) ? $c->get(ICorrectiveActionRepository::class) : null,
                $c->get(ReportingSettings::class),
            ),
            ReportingPageData::class => static fn(ContainerInterface $c): ReportingPageData => new ReportingPageData(
                $c->get(ReportingSnapshotService::class),
                $c->get(PlatformAggregateMetricsService::class),
                $c->get(INationalStatisticRepository::class),
                $c->has(ContentPageReader::class) ? $c->get(ContentPageReader::class) : null,
            ),
            ReportingPageController::class => static fn(ContainerInterface $c): ReportingPageController => new ReportingPageController(
                $c->get(ISessionService::class),
                $c->get(IPermissionService::class),
                $c->get(ViewRenderer::class),
                $c->get(ReportingPageData::class),
            ),
            ReportingApiController::class => static fn(ContainerInterface $c): ReportingApiController => new ReportingApiController(
                $c->get(ReportingSnapshotService::class),
                $c->get(GeneratePdf::class),
                $c->get(GenerateCsv::class),
                $c->get(RegenerateReportArtifactUseCase::class),
                $c->get(IReportArtifactRepository::class),
                $c->get(IssueSignedReportAccessUseCase::class),
                $c->get(ReadSignedReportAccessUseCase::class),
                $c->get(IStorageService::class),
                $c->get(ISessionService::class),
                $c->get(IAuditService::class),
            ),
            GeneratePdf::class => static fn(ContainerInterface $c): GeneratePdf => new GeneratePdf(
                $c->get(ReportingSnapshotService::class),
                $c->get(ReportArtifactService::class),
                $c->get(IStorageService::class),
                $c->get(ReportingSettings::class),
                $c->get(ISessionService::class),
                $c->get(SettingsService::class),
                $c->has(ContentPageReader::class) ? $c->get(ContentPageReader::class) : null,
                $c->get(PlatformAggregateMetricsService::class),
                $c->get(INationalStatisticRepository::class),
            ),
            GenerateCsv::class => static fn(ContainerInterface $c): GenerateCsv => new GenerateCsv(
                $c->get(ReportingSnapshotService::class),
                $c->get(ReportArtifactService::class),
                $c->get(IStorageService::class),
                $c->get(ReportingSettings::class),
                $c->get(ISessionService::class),
            ),
            INationalStatisticRepository::class => static fn(ContainerInterface $c): INationalStatisticRepository => new NationalStatisticRepository(
                $c->get(Connection::class),
                $c->get(IClock::class),
            ),
            IPlatformAggregateMetricRepository::class => static fn(ContainerInterface $c): IPlatformAggregateMetricRepository => new PlatformAggregateMetricRepository(
                $c->get(Connection::class),
            ),
            PlatformAggregateMetricsService::class => static fn(ContainerInterface $c): PlatformAggregateMetricsService => new PlatformAggregateMetricsService(
                $c->get(Connection::class),
                $c->get(IPlatformAggregateMetricRepository::class),
                $c->get(IClock::class),
            ),
            CreateNationalStatisticUseCase::class => static fn(ContainerInterface $c): CreateNationalStatisticUseCase => new CreateNationalStatisticUseCase(
                $c->get(INationalStatisticRepository::class),
                $c->get(IPermissionService::class),
                $c->get(IAuditService::class),
                $c->get(IClock::class),
            ),
            UpdateNationalStatisticUseCase::class => static fn(ContainerInterface $c): UpdateNationalStatisticUseCase => new UpdateNationalStatisticUseCase(
                $c->get(INationalStatisticRepository::class),
                $c->get(IPermissionService::class),
                $c->get(IAuditService::class),
            ),
            DeleteNationalStatisticUseCase::class => static fn(ContainerInterface $c): DeleteNationalStatisticUseCase => new DeleteNationalStatisticUseCase(
                $c->get(INationalStatisticRepository::class),
                $c->get(IPermissionService::class),
                $c->get(IAuditService::class),
            ),
            NationalStatisticAdminController::class => static fn(ContainerInterface $c): NationalStatisticAdminController => new NationalStatisticAdminController(
                $c->get(INationalStatisticRepository::class),
                $c->get(CreateNationalStatisticUseCase::class),
                $c->get(UpdateNationalStatisticUseCase::class),
                $c->get(DeleteNationalStatisticUseCase::class),
                $c->get(IPermissionService::class),
                $c->get(ISessionService::class),
            ),
            NationalMetricsRefreshCommand::class => static fn(ContainerInterface $c): NationalMetricsRefreshCommand => new NationalMetricsRefreshCommand(
                $c->get(PlatformAggregateMetricsService::class),
                $c->get(CommandLockRunner::class),
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
        return new ReportingPermissionDefinitionProvider();
    }

    public function getSettingsProvider(): mixed
    {
        return new ReportingSettingsProvider();
    }

    public function getConsoleCommandProvider(): mixed
    {
        return new ReportingConsoleCommandProvider();
    }

    public function boot(ContainerInterface $container): void {}
}
