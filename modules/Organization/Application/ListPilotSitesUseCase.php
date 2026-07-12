<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IPilotSiteRepository;
use WorkEddy\Modules\Organization\Domain\PilotSite;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class ListPilotSitesUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IPilotSiteRepository $pilotSites,
        private readonly IPermissionService $permissions,
    ) {}

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function execute(string $organizationUuid, UserContext $actor, array $filters = []): array
    {
        $this->permissions->requirePrivilege($actor, OrganizationPermissions::VIEW);

        $organization = $this->organizations->findByUuid(UuidSupport::requireValid($organizationUuid, 'organizationId'));
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        $items = $this->pilotSites->findAllByOrganizationId((int) $organization->getId(), $filters);

        return array_map(fn(PilotSite $pilotSite): array => [
            'id' => $pilotSite->getUuid(),
            'organizationId' => $pilotSite->getOrganizationUuid(),
            'worksiteId' => $pilotSite->getWorksiteUuid(),
            'enrollmentDate' => $pilotSite->getEnrollmentDate(),
            'pilotStatus' => $pilotSite->getPilotStatus(),
            'targetWorkerCount' => $pilotSite->getTargetWorkerCount(),
            'actualWorkerCount' => $pilotSite->getActualWorkerCount(),
            'industry' => $pilotSite->getIndustry(),
            'notes' => $pilotSite->getNotes(),
        ], $items);
    }
}
