<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice;

use Psr\Container\ContainerInterface;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\WorkerVoice\Application\GetWorkerFeedbackTrendsUseCase;
use WorkEddy\Modules\WorkerVoice\Application\GetSupervisorFeedbackTrendsUseCase;
use WorkEddy\Modules\WorkerVoice\Application\GetWorkerFeedbackUseCase;
use WorkEddy\Modules\WorkerVoice\Application\ListWorkerFeedbackUseCase;
use WorkEddy\Modules\WorkerVoice\Application\Services\SupervisorFeedbackTrendService;
use WorkEddy\Modules\WorkerVoice\Application\Services\WorkerFeedbackTrendService;
use WorkEddy\Modules\WorkerVoice\Application\Services\WorkerFeedbackViewService;
use WorkEddy\Modules\WorkerVoice\Application\SubmitSupervisorFeedbackUseCase;
use WorkEddy\Modules\WorkerVoice\Application\SubmitWorkerFeedbackUseCase;
use WorkEddy\Modules\WorkerVoice\Authorization\WorkerVoicePermissionDefinitionProvider;
use WorkEddy\Modules\WorkerVoice\Domain\Contracts\ISupervisorFeedbackRepository;
use WorkEddy\Modules\WorkerVoice\Domain\Contracts\IWorkerVoiceRepository;
use WorkEddy\Modules\WorkerVoice\Infrastructure\SupervisorFeedbackRepository;
use WorkEddy\Modules\WorkerVoice\Infrastructure\WorkerVoiceRepository;
use WorkEddy\Modules\WorkerVoice\Presentation\WorkerVoiceController;
use WorkEddy\Modules\WorkerVoice\Presentation\WorkerVoicePageController;
use WorkEddy\Modules\WorkerVoice\Presentation\WorkerVoicePageData;
use WorkEddy\Modules\WorkerVoice\Settings\WorkerVoiceSettings;
use WorkEddy\Modules\WorkerVoice\Settings\WorkerVoiceSettingsProvider;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingsService;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'worker_voice';
    }

    public function getDefinitions(): array
    {
        return [
            IWorkerVoiceRepository::class => \DI\autowire(WorkerVoiceRepository::class),
            ISupervisorFeedbackRepository::class => \DI\autowire(SupervisorFeedbackRepository::class),
            WorkerVoiceSettings::class => static fn(ContainerInterface $c): WorkerVoiceSettings => new WorkerVoiceSettings($c->get(SettingsService::class)),
            WorkerFeedbackViewService::class => \DI\autowire(),
            WorkerFeedbackTrendService::class => \DI\autowire(),
            SupervisorFeedbackTrendService::class => \DI\autowire(),
            SubmitWorkerFeedbackUseCase::class => \DI\autowire()
                ->constructorParameter('worksites', \DI\get(IWorksiteRepository::class))
                ->constructorParameter('departments', \DI\get(IDepartmentRepository::class))
                ->constructorParameter('jobRoles', \DI\get(IJobRoleRepository::class)),
            SubmitSupervisorFeedbackUseCase::class => \DI\autowire()
                ->constructorParameter('worksites', \DI\get(IWorksiteRepository::class))
                ->constructorParameter('departments', \DI\get(IDepartmentRepository::class))
                ->constructorParameter('jobRoles', \DI\get(IJobRoleRepository::class)),
            GetWorkerFeedbackUseCase::class => \DI\autowire(),
            ListWorkerFeedbackUseCase::class => \DI\autowire(),
            GetWorkerFeedbackTrendsUseCase::class => \DI\autowire(),
            GetSupervisorFeedbackTrendsUseCase::class => \DI\autowire(),
            WorkerVoicePageData::class => \DI\autowire(),
            WorkerVoiceController::class => \DI\autowire(),
            WorkerVoicePageController::class => \DI\autowire(),
        ];
    }

    public function getRouteFile(): ?string
    {
        return __DIR__ . '/Presentation/routes.php';
    }

    public function getEventListeners(): array { return []; }
    public function getJobHandlers(): array { return []; }
    public function getPermissionDefinitionProvider(): ?IPermissionDefinitionProvider { return new WorkerVoicePermissionDefinitionProvider(); }
    public function getSettingsProvider(): ?IModuleSettingsProvider { return new WorkerVoiceSettingsProvider(); }
    public function getConsoleCommandProvider(): mixed { return null; }
    public function boot(ContainerInterface $container): void { unset($container); }
}
