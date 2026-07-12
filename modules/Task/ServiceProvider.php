<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Task;

use WorkEddy\Modules\Task\Authorization\TaskPermissionDefinitionProvider;
use WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository;
use WorkEddy\Modules\Task\Infrastructure\TaskRepository;
use WorkEddy\Modules\Task\Settings\TaskSettingsProvider;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use Psr\Container\ContainerInterface;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'task';
    }

    public function getDefinitions(): array
    {
        return [
            ITaskRepository::class => \DI\autowire(TaskRepository::class),
            \WorkEddy\Modules\Task\Application\CreateTaskUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Task\Application\ListTasksUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Task\Application\UpdateTaskUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Task\Presentation\TaskController::class => \DI\autowire(),
            \WorkEddy\Modules\Task\Presentation\TaskPageController::class => \DI\autowire(),
            \WorkEddy\Modules\Task\Presentation\TaskPageData::class => \DI\autowire(),
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
        return new TaskPermissionDefinitionProvider();
    }

    public function getSettingsProvider(): ?IModuleSettingsProvider
    {
        return new TaskSettingsProvider();
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
