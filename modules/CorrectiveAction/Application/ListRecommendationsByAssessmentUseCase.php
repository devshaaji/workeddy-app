<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Support\UuidSupport;

final class ListRecommendationsByAssessmentUseCase
{
    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly IPermissionService $permissions,
    ) {}

    /** @return list<array<string, mixed>> */
    public function execute(string $assessmentUuid, UserContext $actor): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::REVIEW_RECOMMENDATIONS);
        $assessmentUuid = UuidSupport::requireValid($assessmentUuid, 'assessmentUuid');

        return array_values(array_map(
            static fn($recommendation): array => $recommendation->toView(),
            array_filter(
                $this->repository->listRecommendationsByAssessment($assessmentUuid),
                static fn($recommendation): bool => $actor->organizationId === null || $recommendation->organizationId === $actor->organizationId,
            ),
        ));
    }
}
