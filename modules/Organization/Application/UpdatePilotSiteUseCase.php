<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IPilotSiteRepository;
use WorkEddy\Modules\Organization\Domain\PilotSite;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class UpdatePilotSiteUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IPilotSiteRepository $pilotSites,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
    ) {}

    /** @return array<string, mixed> */
    public function execute(
        string $organizationUuid,
        string $pilotSiteUuid,
        UserContext $actor,
        ?string $enrollmentDate = null,
        ?string $pilotStatus = null,
        ?int $targetWorkerCount = null,
        ?int $actualWorkerCount = null,
        ?string $industry = null,
        ?string $notes = null,
    ): array {
        $this->permissions->requirePrivilege($actor, OrganizationPermissions::STRUCTURE_MANAGE);

        $organization = $this->organizations->findByUuid(UuidSupport::requireValid($organizationUuid, 'organizationId'));
        if ($organization === null) {
            throw new NotFoundException('Organization not found.');
        }

        $existing = $this->pilotSites->findByUuid(UuidSupport::requireValid($pilotSiteUuid, 'pilotSiteId'));
        if ($existing === null || $existing->getOrganizationId() !== $organization->getId()) {
            throw new NotFoundException('Pilot site not found for organization.');
        }

        $updated = new PilotSite(
            id: $existing->getId(),
            uuid: $existing->getUuid(),
            organizationId: $existing->getOrganizationId(),
            organizationUuid: $existing->getOrganizationUuid(),
            worksiteId: $existing->getWorksiteId(),
            worksiteUuid: $existing->getWorksiteUuid(),
            enrollmentDate: $this->resolveDate($enrollmentDate, $existing->getEnrollmentDate()),
            pilotStatus: $this->resolveStatus($pilotStatus, $existing->getPilotStatus()),
            targetWorkerCount: $this->resolveCount($targetWorkerCount, $existing->getTargetWorkerCount(), 'targetWorkerCount'),
            actualWorkerCount: $this->resolveCount($actualWorkerCount, $existing->getActualWorkerCount(), 'actualWorkerCount'),
            industry: $industry !== null ? StructureInput::optionalString($industry) : $existing->getIndustry(),
            notes: $notes !== null ? StructureInput::optionalString($notes) : $existing->getNotes(),
            createdAt: $existing->getCreatedAt(),
        );

        $before = $this->toView($existing);
        $this->tx->transactional(function () use ($updated, $before, $actor): void {
            $this->pilotSites->update($updated);
            $this->audit->record(
                action: 'organization.pilot_site.updated',
                entityType: 'PilotSite',
                entityId: $updated->getUuid(),
                beforeState: $before,
                afterState: $this->toView($updated),
                actorId: (string) $actor->userId,
                actorType: 'User',
            );
        });

        return $this->toView($updated);
    }

    private function resolveDate(?string $value, string $current): string
    {
        if ($value === null) {
            return $current;
        }

        $normalized = trim($value);
        if ($normalized === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
            throw new ValidationException(['enrollmentDate' => 'Enrollment date must use YYYY-MM-DD.']);
        }

        return $normalized;
    }

    private function resolveStatus(?string $value, string $current): string
    {
        if ($value === null) {
            return $current;
        }

        $normalized = strtolower(trim($value));
        if (!in_array($normalized, ['enrolled', 'active', 'paused', 'completed'], true)) {
            throw new ValidationException(['pilotStatus' => 'Pilot status must be enrolled, active, paused, or completed.']);
        }

        return $normalized;
    }

    private function resolveCount(?int $value, int $current, string $field): int
    {
        if ($value === null) {
            return $current;
        }
        if ($value < 0) {
            throw new ValidationException([$field => 'Worker count cannot be negative.']);
        }

        return $value;
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
