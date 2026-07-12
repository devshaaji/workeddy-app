<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class ListDepartmentsUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IDepartmentRepository $departments,
        private readonly \WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository $worksites,
        private readonly IPermissionService $permissions,
    ) {}

    /**
     * @return list<array{id: string, organizationId: string, worksiteId: ?string, parentDepartmentId: ?string, name: string, status: string}>
     */
    public function execute(string $organizationUuid, UserContext $actor, int $limit = 50, int $offset = 0): array
    {
        $this->permissions->requirePrivilege($actor, OrganizationPermissions::VIEW);

        $organization = $this->organizations->findByUuid(UuidSupport::requireValid($organizationUuid, 'organizationId'));
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        $departments = $this->departments->findAllByOrganizationId((int) $organization->getId(), $limit, $offset);
        $worksiteUuids = $this->uuidMap($this->worksites->findAllByOrganizationId((int) $organization->getId(), 500, 0));
        $departmentUuids = $this->uuidMap($departments);

        return array_map(
            static fn($department): array => [
                'id' => $department->getUuid(),
                'organizationId' => $organization->getUuid(),
                'worksiteId' => $department->getWorksiteId() !== null ? ($worksiteUuids[$department->getWorksiteId()] ?? null) : null,
                'parentDepartmentId' => $department->getParentDepartmentId() !== null ? ($departmentUuids[$department->getParentDepartmentId()] ?? null) : null,
                'name' => $department->getName(),
                'status' => $department->getStatus(),
            ],
            $departments,
        );
    }

    /**
     * @param iterable<object> $items
     * @return array<int, string>
     */
    private function uuidMap(iterable $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $id = method_exists($item, 'getId') ? $item->getId() : null;
            $uuid = method_exists($item, 'getUuid') ? $item->getUuid() : null;
            if ($id !== null && is_string($uuid)) {
                $map[(int) $id] = $uuid;
            }
        }

        return $map;
    }
}
