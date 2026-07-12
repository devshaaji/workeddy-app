<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class SubmitAssessmentForReviewUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $assessmentUuid, UserContext $actor): array
    {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::UPDATE);
        $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'));
        if ($assessment === null || ($actor->organizationId !== null && $actor->organizationId !== $assessment->getOrganizationId())) {
            throw new NotFoundException('Assessment not found.');
        }

        $submitted = $assessment->markSubmitted();
        $this->tx->transactional(fn() => $this->assessments->update($submitted));
        $this->audit->record('assessment.submitted', 'assessment', $submitted->getUuid(), afterState: $submitted->toView(), actorId: (string) $actor->userId, actorType: 'user');

        return $submitted->toView();
    }
}
