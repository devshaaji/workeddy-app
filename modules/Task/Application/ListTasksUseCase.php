<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Task\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Task\Authorization\TaskPermissions;
use WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class ListTasksUseCase
{
    private readonly TaskViewFactory $views;

    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly ITaskRepository $tasks,
        IWorksiteRepository $worksites,
        IDepartmentRepository $departments,
        IJobRoleRepository $jobRoles,
        AssessmentEngine $engine,
        private readonly IPermissionService $permissions,
    ) {
        $this->views = new TaskViewFactory($organizations, $worksites, $departments, $jobRoles, $engine);
    }

    public function execute(string $organizationUuid, UserContext $actor, int $limit = 50, int $offset = 0): array
    {
        $this->permissions->requirePrivilege($actor, TaskPermissions::VIEW);

        $organization = $this->organizations->findByUuid($organizationUuid);
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        return array_map(
            fn($task): array => $this->views->make($task),
            $this->tasks->findAllByOrganizationId($organization->getId() ?? 0, $limit, $offset),
        );
    }
}
