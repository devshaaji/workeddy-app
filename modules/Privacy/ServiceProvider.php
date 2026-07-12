<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy;

use Psr\Container\ContainerInterface;
use WorkEddy\Modules\Privacy\Application\EnforceVideoRetentionUseCase;
use WorkEddy\Modules\Privacy\Application\LogVideoAccessUseCase;
use WorkEddy\Modules\Privacy\Application\IssueSignedVideoAccessUseCase;
use WorkEddy\Modules\Privacy\Application\ReadSignedVideoAccessUseCase;
use WorkEddy\Modules\Privacy\Application\RecordVideoConsentUseCase;
use WorkEddy\Modules\Privacy\Application\UpdateRetentionPolicyUseCase;
use WorkEddy\Modules\Privacy\Authorization\PrivacyPermissionDefinitionProvider;
use WorkEddy\Modules\Privacy\Console\EnforceVideoRetentionCommand;
use WorkEddy\Modules\Privacy\Console\PrivacyConsoleCommandProvider;
use WorkEddy\Modules\Privacy\Domain\Contracts\IPrivacyRepository;
use WorkEddy\Modules\Privacy\Infrastructure\PrivacyRepository;
use WorkEddy\Modules\Privacy\Presentation\PrivacyController;
use WorkEddy\Modules\Privacy\Presentation\PrivacyPageController;
use WorkEddy\Modules\Privacy\Presentation\PrivacyPageData;
use WorkEddy\Modules\Privacy\Settings\PrivacySettingsProvider;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Settings\IModuleSettingsProvider;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'privacy';
    }

    public function getDefinitions(): array
    {
        return [
            IPrivacyRepository::class => \DI\autowire(PrivacyRepository::class),
            RecordVideoConsentUseCase::class => \DI\autowire(),
            LogVideoAccessUseCase::class => \DI\autowire(),
            UpdateRetentionPolicyUseCase::class => \DI\autowire(),
            EnforceVideoRetentionUseCase::class => \DI\autowire(),
            IssueSignedVideoAccessUseCase::class => \DI\autowire(),
            ReadSignedVideoAccessUseCase::class => \DI\autowire(),
            EnforceVideoRetentionCommand::class => \DI\autowire(),
            PrivacyController::class => \DI\autowire(),
            PrivacyPageController::class => \DI\autowire(),
            PrivacyPageData::class => \DI\autowire(),
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
        return new PrivacyPermissionDefinitionProvider();
    }

    public function getSettingsProvider(): ?IModuleSettingsProvider
    {
        return new PrivacySettingsProvider();
    }

    public function getConsoleCommandProvider(): mixed
    {
        return new PrivacyConsoleCommandProvider();
    }

    public function boot(ContainerInterface $container): void
    {
        unset($container);
    }
}
