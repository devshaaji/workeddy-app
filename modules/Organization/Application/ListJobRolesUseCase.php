<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class ListJobRolesUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IJobRoleRepository $jobRoles,
        private readonly IDepartmentRepository $departments,
        private readonly IPermissionService $permissions,
    ) {}

    /**
     * @return list<array{id: string, organizationId: string, departmentId: ?string, name: string, status: string}>
     */
    public function execute(string $organizationUuid, UserContext $actor, int $limit = 50, int $offset = 0): array
    {
        $this->permissions->requirePrivilege($actor, OrganizationPermissions::VIEW);

        $organization = $this->organizations->findByUuid(UuidSupport::requireValid($organizationUuid, 'organizationId'));
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        $jobRoles = $this->jobRoles->findAllByOrganizationId((int) $organization->getId(), $limit, $offset);
        $departmentUuids = [];
        foreach ($this->departments->findAllByOrganizationId((int) $organization->getId(), 500, 0) as $department) {
            $departmentUuids[(int) $department->getId()] = $department->getUuid();
        }

        return array_map(
            static fn($jobRole): array => [
                'id' => $jobRole->getUuid(),
                'organizationId' => $organization->getUuid(),
                'departmentId' => $jobRole->getDepartmentId() !== null ? ($departmentUuids[$jobRole->getDepartmentId()] ?? null) : null,
                'name' => $jobRole->getName(),
                'status' => $jobRole->getStatus(),
            ],
            $jobRoles,
        );
    }
}
