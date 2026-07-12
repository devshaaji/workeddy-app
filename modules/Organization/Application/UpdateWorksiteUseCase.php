<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Organization\Domain\Worksite;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class UpdateWorksiteUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IWorksiteRepository $worksites,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
    ) {}

    /**
     * @return array{id: string, organizationId: string, name: string, status: string, location: ?string}
     */
    public function execute(string $organizationUuid, string $worksiteUuid, UserContext $actor, string $name, ?string $status = null, ?string $location = null): array
    {
        $this->permissions->requirePrivilege($actor, OrganizationPermissions::STRUCTURE_MANAGE);

        $organization = $this->organizations->findByUuid(UuidSupport::requireValid($organizationUuid, 'organizationId'));
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        $existing = $this->worksites->findByUuid(UuidSupport::requireValid($worksiteUuid, 'worksiteId'));
        if ($existing === null || $existing->getOrganizationId() !== (int) $organization->getId()) {
            throw new NotFoundException('Worksite not found.');
        }

        $worksite = new Worksite(
            id: $existing->getId(),
            uuid: $existing->getUuid(),
            organizationId: $existing->getOrganizationId(),
            name: StructureInput::requireName($name),
            status: StructureInput::optionalStatus($status) ?? $existing->getStatus(),
            location: StructureInput::optionalString($location) ?? $existing->getLocation(),
            createdAt: $existing->getCreatedAt(),
        );

        $this->tx->transactional(function () use ($worksite, $organization, $existing, $actor): void {
            $this->worksites->update($worksite);
            $this->audit->record(
                action: 'organization.worksite.updated',
                entityType: 'Worksite',
                entityId: $worksite->getUuid(),
                beforeState: [
                    'name' => $existing->getName(),
                    'status' => $existing->getStatus(),
                    'location' => $existing->getLocation(),
                ],
                afterState: [
                    'organizationUuid' => $organization->getUuid(),
                    'name' => $worksite->getName(),
                    'status' => $worksite->getStatus(),
                    'location' => $worksite->getLocation(),
                ],
                actorId: (string) $actor->userId,
                actorType: 'User',
            );
        });

        return [
            'id' => $worksite->getUuid(),
            'organizationId' => $organization->getUuid(),
            'name' => $worksite->getName(),
            'status' => $worksite->getStatus(),
            'location' => $worksite->getLocation(),
        ];
    }
}
