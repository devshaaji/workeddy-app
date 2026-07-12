<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\JobRole;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class UpdateJobRoleUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IJobRoleRepository $jobRoles,
        private readonly IDepartmentRepository $departments,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
    ) {}

    /**
     * @return array{id: string, organizationId: string, departmentId: ?string, name: string, status: string}
     */
    public function execute(string $organizationUuid, string $jobRoleUuid, UserContext $actor, string $name, ?string $status = null, ?string $departmentUuid = null): array
    {
        $this->permissions->requirePrivilege($actor, OrganizationPermissions::STRUCTURE_MANAGE);

        $organization = $this->organizations->findByUuid(UuidSupport::requireValid($organizationUuid, 'organizationId'));
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        $existing = $this->jobRoles->findByUuid(UuidSupport::requireValid($jobRoleUuid, 'jobRoleId'));
        if ($existing === null || $existing->getOrganizationId() !== (int) $organization->getId()) {
            throw new NotFoundException('Job role not found.');
        }

        $department = $this->resolveDepartment($organization->getId(), $departmentUuid);
        $jobRole = new JobRole(
            id: $existing->getId(),
            uuid: $existing->getUuid(),
            organizationId: $existing->getOrganizationId(),
            departmentId: $department?->getId(),
            name: StructureInput::requireName($name),
            status: StructureInput::optionalStatus($status) ?? $existing->getStatus(),
            createdAt: $existing->getCreatedAt(),
        );

        $this->tx->transactional(function () use ($jobRole, $organization, $existing, $departmentUuid, $actor): void {
            $this->jobRoles->update($jobRole);
            $this->audit->record(
                action: 'organization.job_role.updated',
                entityType: 'JobRole',
                entityId: $jobRole->getUuid(),
                beforeState: [
                    'name' => $existing->getName(),
                    'status' => $existing->getStatus(),
                    'departmentId' => $existing->getDepartmentId(),
                ],
                afterState: [
                    'organizationUuid' => $organization->getUuid(),
                    'name' => $jobRole->getName(),
                    'status' => $jobRole->getStatus(),
                    'departmentUuid' => $departmentUuid,
                ],
                actorId: (string) $actor->userId,
                actorType: 'User',
            );
        });

        return [
            'id' => $jobRole->getUuid(),
            'organizationId' => $organization->getUuid(),
            'departmentId' => $departmentUuid,
            'name' => $jobRole->getName(),
            'status' => $jobRole->getStatus(),
        ];
    }

    private function resolveDepartment(?int $organizationId, ?string $departmentUuid): ?\WorkEddy\Modules\Organization\Domain\Department
    {
        $uuid = StructureInput::optionalUuid($departmentUuid, 'departmentId');
        if ($uuid === null) {
            return null;
        }

        $department = $this->departments->findByUuid($uuid);
        if ($department === null || $department->getOrganizationId() !== (int) $organizationId) {
            throw new NotFoundException('Department not found.');
        }

        return $department;
    }
}
