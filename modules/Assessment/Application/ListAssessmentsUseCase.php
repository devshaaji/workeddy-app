<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class ListAssessmentsUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IAssessmentRepository $assessments,
        private readonly IPermissionService $permissions,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(string $organizationUuid, UserContext $actor, int $limit = 50, int $offset = 0, ?string $status = null): array
    {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::VIEW);

        if ($organizationUuid === '') {
            $organizationId = null;
        } else {
            $organization = $this->organizations->findByUuid(UuidSupport::requireValid($organizationUuid, 'organizationUuid'));
            if ($organization === null || $organization->getId() === null || ($actor->organizationId !== null && $actor->organizationId !== $organization->getId())) {
                throw new NotFoundException('Organization not found.');
            }
            $organizationId = (int) $organization->getId();
        }

        $assessments = $this->assessments->findAllByOrganizationId($organizationId, max(1, min(100, $limit)), max(0, $offset));
        if ($status !== null && trim($status) !== '') {
            $wanted = trim($status);
            $assessments = array_values(array_filter(
                $assessments,
                static fn($assessment): bool => $assessment->getStatus() === $wanted,
            ));
        }

        return array_map(
            static fn($assessment): array => $assessment->toView(),
            $assessments,
        );
    }
}
