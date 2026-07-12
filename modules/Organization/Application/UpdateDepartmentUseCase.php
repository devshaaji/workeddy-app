<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Organization\Domain\Department;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class UpdateDepartmentUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IDepartmentRepository $departments,
        private readonly IWorksiteRepository $worksites,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
    ) {}

    /**
     * @return array{id: string, organizationId: string, worksiteId: ?string, parentDepartmentId: ?string, name: string, status: string}
     */
    public function execute(string $organizationUuid, string $departmentUuid, UserContext $actor, string $name, ?string $status = null, ?string $worksiteUuid = null, ?string $parentDepartmentUuid = null): array
    {
        $this->permissions->requirePrivilege($actor, OrganizationPermissions::STRUCTURE_MANAGE);

        $organization = $this->organizations->findByUuid(UuidSupport::requireValid($organizationUuid, 'organizationId'));
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        $existing = $this->departments->findByUuid(UuidSupport::requireValid($departmentUuid, 'departmentId'));
        if ($existing === null || $existing->getOrganizationId() !== (int) $organization->getId()) {
            throw new NotFoundException('Department not found.');
        }

        $worksite = $this->resolveWorksite($organization->getId(), $worksiteUuid);
        $parent = $this->resolveParentDepartment($organization->getId(), $parentDepartmentUuid, $existing->getUuid());

        $department = new Department(
            id: $existing->getId(),
            uuid: $existing->getUuid(),
            organizationId: $existing->getOrganizationId(),
            worksiteId: $worksite?->getId(),
            parentDepartmentId: $parent?->getId(),
            name: StructureInput::requireName($name),
            status: StructureInput::optionalStatus($status) ?? $existing->getStatus(),
            createdAt: $existing->getCreatedAt(),
        );

        $this->tx->transactional(function () use ($department, $organization, $existing, $worksiteUuid, $parentDepartmentUuid, $actor): void {
            $this->departments->update($department);
            $this->audit->record(
                action: 'organization.department.updated',
                entityType: 'Department',
                entityId: $department->getUuid(),
                beforeState: [
                    'name' => $existing->getName(),
                    'status' => $existing->getStatus(),
                    'worksiteId' => $existing->getWorksiteId(),
                    'parentDepartmentId' => $existing->getParentDepartmentId(),
                ],
                afterState: [
                    'organizationUuid' => $organization->getUuid(),
                    'name' => $department->getName(),
                    'status' => $department->getStatus(),
                    'worksiteUuid' => $worksiteUuid,
                    'parentDepartmentUuid' => $parentDepartmentUuid,
                ],
                actorId: (string) $actor->userId,
                actorType: 'User',
            );
        });

        return [
            'id' => $department->getUuid(),
            'organizationId' => $organization->getUuid(),
            'worksiteId' => $worksiteUuid,
            'parentDepartmentId' => $parentDepartmentUuid,
            'name' => $department->getName(),
            'status' => $department->getStatus(),
        ];
    }

    private function resolveWorksite(?int $organizationId, ?string $worksiteUuid): ?\WorkEddy\Modules\Organization\Domain\Worksite
    {
        $uuid = StructureInput::optionalUuid($worksiteUuid, 'worksiteId');
        if ($uuid === null) {
            return null;
        }

        $worksite = $this->worksites->findByUuid($uuid);
        if ($worksite === null || $worksite->getOrganizationId() !== (int) $organizationId) {
            throw new NotFoundException('Worksite not found.');
        }

        return $worksite;
    }

    private function resolveParentDepartment(?int $organizationId, ?string $departmentUuid, string $currentDepartmentUuid): ?Department
    {
        $uuid = StructureInput::optionalUuid($departmentUuid, 'parentDepartmentId');
        if ($uuid === null) {
            return null;
        }

        if ($uuid === $currentDepartmentUuid) {
            throw new \WorkEddy\Shared\Exceptions\ValidationException(['parentDepartmentId' => 'Department cannot be its own parent.']);
        }

        $department = $this->departments->findByUuid($uuid);
        if ($department === null || $department->getOrganizationId() !== (int) $organizationId) {
            throw new NotFoundException('Parent department not found.');
        }

        return $department;
    }
}
