<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class ListWorksitesUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IWorksiteRepository $worksites,
        private readonly IPermissionService $permissions,
    ) {}

    /**
     * @return list<array{id: string, organizationId: string, name: string, status: string, location: ?string}>
     */
    public function execute(string $organizationUuid, UserContext $actor, int $limit = 50, int $offset = 0): array
    {
        $this->permissions->requirePrivilege($actor, OrganizationPermissions::VIEW);

        $organization = $this->organizations->findByUuid(UuidSupport::requireValid($organizationUuid, 'organizationId'));
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        return array_map(
            static fn($worksite): array => [
                'id' => $worksite->getUuid(),
                'organizationId' => $organization->getUuid(),
                'name' => $worksite->getName(),
                'status' => $worksite->getStatus(),
                'location' => $worksite->getLocation(),
            ],
            $this->worksites->findAllByOrganizationId((int) $organization->getId(), $limit, $offset),
        );
    }
}
