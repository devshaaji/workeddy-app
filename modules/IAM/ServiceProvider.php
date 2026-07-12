<?php

/**
 * IAM module service provider.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM;

use WorkEddy\Modules\IAM\Authorization\IAMPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Modules\IAM\Settings\IAMSettingsProvider;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use Psr\Container\ContainerInterface;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'iam';
    }

    public function getDefinitions(): array
    {
        return [
            \WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService::class => \DI\autowire(\WorkEddy\Modules\IAM\Application\Services\PermissionService::class),
            \WorkEddy\Platform\Authorization\IAuthorizationService::class => \DI\autowire(\WorkEddy\Modules\IAM\Application\Services\SessionAuthorizationService::class),
            \WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository::class => \DI\autowire(\WorkEddy\Modules\IAM\Infrastructure\UserRepository::class),
            \WorkEddy\Modules\IAM\Domain\Contracts\IUserProfileRepository::class => \DI\autowire(\WorkEddy\Modules\IAM\Infrastructure\UserProfileRepository::class),
            \WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository::class => \DI\autowire(\WorkEddy\Modules\IAM\Infrastructure\OrganizationMembershipRepository::class),
            \WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository::class => \DI\autowire(\WorkEddy\Modules\IAM\Infrastructure\RoleRepository::class),
            \WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository::class => \DI\autowire(\WorkEddy\Modules\IAM\Infrastructure\PermissionRepository::class),
            \WorkEddy\Modules\IAM\Domain\Contracts\IAuthService::class => \DI\autowire(\WorkEddy\Modules\IAM\Application\Services\AuthService::class),
            \WorkEddy\Modules\IAM\Domain\Contracts\IUserContextService::class => \DI\autowire(\WorkEddy\Modules\IAM\Application\Services\UserContextService::class),
            \WorkEddy\Modules\IAM\Domain\Contracts\IUserContactQueryService::class => \DI\autowire(\WorkEddy\Modules\IAM\Application\Services\UserContactQueryService::class),
            \WorkEddy\Modules\IAM\Domain\Contracts\IDepartmentScopeAuthorizer::class => \DI\autowire(\WorkEddy\Modules\IAM\Application\Services\DepartmentScopeAuthorizer::class),
            \WorkEddy\Modules\IAM\Application\Services\UserInvitationSenderInterface::class => \DI\autowire(\WorkEddy\Modules\IAM\Application\Services\PasswordResetUserInvitationSender::class),
            \WorkEddy\Modules\IAM\Application\Services\ModuleUserProvisionerInterface::class => \DI\autowire(\WorkEddy\Modules\IAM\Application\Services\ModuleUserProvisioner::class),
            \WorkEddy\Modules\IAM\Application\Services\ModuleUserProvisioner::class => \DI\autowire(),
            \WorkEddy\Modules\IAM\Application\PublicRegisterUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\IAM\Presentation\UserViewFactory::class => \DI\autowire(),
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
        return new IAMPermissionDefinitionProvider();
    }

    public function getSettingsProvider(): ?IModuleSettingsProvider
    {
        return new IAMSettingsProvider();
    }

    public function getConsoleCommandProvider(): mixed
    {
        return null;
    }

    public function boot(ContainerInterface $container): void {}
}
