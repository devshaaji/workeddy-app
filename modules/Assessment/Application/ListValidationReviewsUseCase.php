<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Assessment\Domain\Contracts\IValidationReviewRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class ListValidationReviewsUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly IValidationReviewRepository $reviews,
        private readonly IPermissionService $permissions,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(string $assessmentUuid, UserContext $actor, bool $finalOnly = false): array
    {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::VIEW);
        $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentId'));
        if ($assessment === null || $assessment->getOrganizationId() !== $actor->organizationId) {
            throw new NotFoundException('Assessment not found.');
        }

        return array_map(
            static fn($review): array => $review->toView(),
            $this->reviews->findByAssessmentUuid($assessment->getUuid(), $finalOnly),
        );
    }
}
