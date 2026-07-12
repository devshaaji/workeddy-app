<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IPilotSiteRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Organization\Domain\PilotSite;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class EnrollPilotSiteUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IWorksiteRepository $worksites,
        private readonly IPilotSiteRepository $pilotSites,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
    ) {}

    /** @return array<string, mixed> */
    public function execute(
        string $organizationUuid,
        string $worksiteUuid,
        string $enrollmentDate,
        UserContext $actor,
        string $pilotStatus = 'enrolled',
        int $targetWorkerCount = 0,
        int $actualWorkerCount = 0,
        ?string $industry = null,
        ?string $notes = null,
    ): array {
        $this->permissions->requirePrivilege($actor, OrganizationPermissions::STRUCTURE_MANAGE);

        $organization = $this->organizations->findByUuid(UuidSupport::requireValid($organizationUuid, 'organizationId'));
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        $worksite = $this->worksites->findByUuid(UuidSupport::requireValid($worksiteUuid, 'worksiteId'));
        if ($worksite === null || $worksite->getOrganizationId() !== $organization->getId()) {
            throw new NotFoundException('Worksite not found for organization.');
        }

        if ($this->pilotSites->findAllByOrganizationId((int) $organization->getId(), ['worksiteUuid' => $worksiteUuid], 1, 0) !== []) {
            throw new ValidationException(['worksiteUuid' => 'Pilot site enrollment already exists for this worksite.']);
        }

        $pilotSite = new PilotSite(
            id: null,
            uuid: UuidSupport::generate(),
            organizationId: (int) $organization->getId(),
            organizationUuid: $organization->getUuid(),
            worksiteId: (int) $worksite->getId(),
            worksiteUuid: $worksite->getUuid(),
            enrollmentDate: $this->requireDate($enrollmentDate),
            pilotStatus: $this->requirePilotStatus($pilotStatus),
            targetWorkerCount: $this->requireCount($targetWorkerCount, 'targetWorkerCount'),
            actualWorkerCount: $this->requireCount($actualWorkerCount, 'actualWorkerCount'),
            industry: StructureInput::optionalString($industry),
            notes: StructureInput::optionalString($notes),
        );

        $this->tx->transactional(function () use ($pilotSite, $actor): void {
            $this->pilotSites->create($pilotSite);
            $this->audit->record(
                action: 'organization.pilot_site.created',
                entityType: 'PilotSite',
                entityId: $pilotSite->getUuid(),
                afterState: $this->toView($pilotSite),
                actorId: (string) $actor->userId,
                actorType: 'User',
            );
        });

        return $this->toView($pilotSite);
    }

    private function requirePilotStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        if (!in_array($normalized, ['enrolled', 'active', 'paused', 'completed'], true)) {
            throw new ValidationException(['pilotStatus' => 'Pilot status must be enrolled, active, paused, or completed.']);
        }

        return $normalized;
    }

    private function requireDate(string $date): string
    {
        $normalized = trim($date);
        if ($normalized === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
            throw new ValidationException(['enrollmentDate' => 'Enrollment date must use YYYY-MM-DD.']);
        }

        return $normalized;
    }

    private function requireCount(int $count, string $field): int
    {
        if ($count < 0) {
            throw new ValidationException([$field => 'Worker count cannot be negative.']);
        }

        return $count;
    }

    /** @return array<string, mixed> */
    private function toView(PilotSite $pilotSite): array
    {
        return [
            'id' => $pilotSite->getUuid(),
            'organizationId' => $pilotSite->getOrganizationUuid(),
            'worksiteId' => $pilotSite->getWorksiteUuid(),
            'enrollmentDate' => $pilotSite->getEnrollmentDate(),
            'pilotStatus' => $pilotSite->getPilotStatus(),
            'targetWorkerCount' => $pilotSite->getTargetWorkerCount(),
            'actualWorkerCount' => $pilotSite->getActualWorkerCount(),
            'industry' => $pilotSite->getIndustry(),
            'notes' => $pilotSite->getNotes(),
        ];
    }
}
