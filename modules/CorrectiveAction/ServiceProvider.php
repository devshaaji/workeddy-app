<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction;

use Psr\Container\ContainerInterface;
use WorkEddy\Modules\CorrectiveAction\Application\AssignCorrectiveActionUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\GenerateRecommendationsUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\GetCorrectiveActionUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ListCorrectiveActionsUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ListCorrectiveActionLibraryUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ListRecommendationRulesUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ListRecommendationsByAssessmentUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ReviewRecommendationUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\RunCorrectiveActionMaintenanceUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ScheduleFollowUpAssessmentUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\SeedCorrectiveActionDefaultsUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\Services\ControlActionWorkflowService;
use WorkEddy\Modules\CorrectiveAction\Application\Services\ControlRecommendationService;
use WorkEddy\Modules\CorrectiveAction\Application\UpdateCorrectiveActionStatusUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\UpsertCorrectiveActionLibraryItemUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\UpsertRecommendationRuleUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\UploadCorrectiveActionEvidenceUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\VerifyCorrectiveActionUseCase;
use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissionDefinitionProvider;
use WorkEddy\Modules\CorrectiveAction\Console\CorrectiveActionConsoleCommandProvider;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\CorrectiveAction\Infrastructure\CorrectiveActionRepository;
use WorkEddy\Modules\CorrectiveAction\Presentation\CorrectiveActionController;
use WorkEddy\Modules\CorrectiveAction\Presentation\CorrectiveActionPageController;
use WorkEddy\Modules\CorrectiveAction\Settings\CorrectiveActionSettingsProvider;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Settings\IModuleSettingsProvider;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'corrective_action';
    }

    public function getDefinitions(): array
    {
        return [
            ICorrectiveActionRepository::class => \DI\autowire(CorrectiveActionRepository::class),
            ControlRecommendationService::class => \DI\autowire(),
            ControlActionWorkflowService::class => \DI\autowire(),
            GenerateRecommendationsUseCase::class => \DI\autowire(),
            ListRecommendationsByAssessmentUseCase::class => \DI\autowire(),
            ListCorrectiveActionsUseCase::class => \DI\autowire(),
            GetCorrectiveActionUseCase::class => \DI\autowire(),
            ReviewRecommendationUseCase::class => \DI\autowire(),
            AssignCorrectiveActionUseCase::class => \DI\autowire(),
            UpdateCorrectiveActionStatusUseCase::class => \DI\autowire(),
            UploadCorrectiveActionEvidenceUseCase::class => \DI\autowire(),
            VerifyCorrectiveActionUseCase::class => \DI\autowire(),
            ScheduleFollowUpAssessmentUseCase::class => \DI\autowire(),
            SeedCorrectiveActionDefaultsUseCase::class => \DI\autowire(),
            RunCorrectiveActionMaintenanceUseCase::class => \DI\autowire(),
            ListCorrectiveActionLibraryUseCase::class => \DI\autowire(),
            UpsertCorrectiveActionLibraryItemUseCase::class => \DI\autowire(),
            ListRecommendationRulesUseCase::class => \DI\autowire(),
            UpsertRecommendationRuleUseCase::class => \DI\autowire(),
            CorrectiveActionController::class => \DI\autowire(),
            CorrectiveActionPageController::class => \DI\autowire(),
        ];
    }

    public function getRouteFile(): ?string
    {
        return __DIR__ . '/Presentation/routes.php';
    }

    public function getEventListeners(): array { return []; }
    public function getJobHandlers(): array { return []; }
    public function getPermissionDefinitionProvider(): ?IPermissionDefinitionProvider { return new CorrectiveActionPermissionDefinitionProvider(); }
    public function getSettingsProvider(): ?IModuleSettingsProvider { return new CorrectiveActionSettingsProvider(); }
    public function getConsoleCommandProvider(): mixed { return new CorrectiveActionConsoleCommandProvider(); }
    public function boot(ContainerInterface $container): void { unset($container); }
}
