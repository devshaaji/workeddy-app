<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Ergonomics;

use Psr\Container\ContainerInterface;
use WorkEddy\Modules\Ergonomics\Application\ScoreErgonomicAssessmentUseCase;
use WorkEddy\Modules\Ergonomics\Authorization\ErgonomicsPermissionDefinitionProvider;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\Ergonomics\Settings\ErgonomicsSettingsProvider;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Settings\IModuleSettingsProvider;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'ergonomics';
    }

    public function getDefinitions(): array
    {
        return [
            AssessmentEngine::class => \DI\autowire(),
            ScoreErgonomicAssessmentUseCase::class => \DI\autowire(),
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
        return new ErgonomicsPermissionDefinitionProvider();
    }

    public function getSettingsProvider(): ?IModuleSettingsProvider
    {
        return new ErgonomicsSettingsProvider();
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
