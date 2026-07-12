<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Organization\Domain\Worksite;
use WorkEddy\Modules\Subscription\Application\Support\SubscriptionMetricCatalog;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionLimitGuard;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRecorder;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class CreateWorksiteUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IWorksiteRepository $worksites,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
        private readonly ISubscriptionLimitGuard $limits,
        private readonly ISubscriptionUsageRecorder $usage,
    ) {}

    /**
     * @return array{id: string, organizationId: string, name: string, status: string, location: ?string}
     */
    public function execute(string $organizationUuid, string $name, UserContext $actor, ?string $location = null): array
    {
        $this->permissions->requirePrivilege($actor, OrganizationPermissions::STRUCTURE_MANAGE);

        $organization = $this->organizations->findByUuid(UuidSupport::requireValid($organizationUuid, 'organizationId'));
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }
        if ($this->limits->wouldExceed((int) $organization->getId(), SubscriptionMetricCatalog::MAX_WORKSITES)) {
            throw new ValidationException(['worksite' => 'Plan worksite limit reached. Upgrade to add more worksites.']);
        }

        $worksite = new Worksite(
            id: null,
            uuid: UuidSupport::generate(),
            organizationId: (int) $organization->getId(),
            name: StructureInput::requireName($name),
            status: 'active',
            location: StructureInput::optionalString($location),
        );

        $this->tx->transactional(function () use ($worksite, $organization, $actor): void {
            $this->worksites->create($worksite);
            $this->usage->forOrganization((int) $organization->getId(), SubscriptionMetricCatalog::MAX_WORKSITES);
            $this->audit->record(
                action: 'organization.worksite.created',
                entityType: 'Worksite',
                entityId: $worksite->getUuid(),
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
