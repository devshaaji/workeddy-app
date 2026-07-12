<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization;

use WorkEddy\Modules\Organization\Authorization\OrganizationPermissionDefinitionProvider;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IPilotSiteRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Organization\Infrastructure\DepartmentRepository;
use WorkEddy\Modules\Organization\Infrastructure\JobRoleRepository;
use WorkEddy\Modules\Organization\Infrastructure\OrganizationRepository;
use WorkEddy\Modules\Organization\Infrastructure\PilotSiteRepository;
use WorkEddy\Modules\Organization\Infrastructure\WorksiteRepository;
use WorkEddy\Modules\Organization\Settings\OrganizationSettingsProvider;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use Psr\Container\ContainerInterface;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'organization';
    }

    public function getDefinitions(): array
    {
        return [
            IOrganizationRepository::class => \DI\autowire(OrganizationRepository::class),
            IWorksiteRepository::class => \DI\autowire(WorksiteRepository::class),
            IPilotSiteRepository::class => \DI\autowire(PilotSiteRepository::class),
            IDepartmentRepository::class => \DI\autowire(DepartmentRepository::class),
            IJobRoleRepository::class => \DI\autowire(JobRoleRepository::class),
            \WorkEddy\Modules\Organization\Application\CreateOrganizationUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\UpdateOrganizationStatusUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\InviteOrganizationMemberUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\CreateWorksiteUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\ListWorksitesUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\UpdateWorksiteUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\EnrollPilotSiteUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\ListPilotSitesUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\UpdatePilotSiteUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\CreateDepartmentUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\ListDepartmentsUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\UpdateDepartmentUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\CreateJobRoleUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\ListJobRolesUseCase::class => \DI\autowire(),
            \WorkEddy\Modules\Organization\Application\UpdateJobRoleUseCase::class => \DI\autowire(),
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
        return new OrganizationPermissionDefinitionProvider();
    }

    public function getSettingsProvider(): ?IModuleSettingsProvider
    {
        return new OrganizationSettingsProvider();
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
