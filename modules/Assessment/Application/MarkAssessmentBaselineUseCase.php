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

final class MarkAssessmentBaselineUseCase
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

        $view = $assessment->toView();
        $updated = $assessment->markBaseline();
        $this->tx->transactional(fn() => $this->assessments->update($updated));
        $result = $updated->toView();
        $this->audit->record('assessment.baseline_marked', 'assessment', $assessment->getUuid(), beforeState: $view, afterState: $result, actorId: (string) $actor->userId, actorType: 'user');

        return $result;
    }
}
