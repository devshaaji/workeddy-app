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
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class ReviewAssessmentUseCase
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
    public function approve(string $assessmentUuid, UserContext $actor, string $reviewerName, ?string $reviewerCredentials = null, ?string $reviewerNotes = null, ?float $adjustedScore = null, ?string $adjustmentReason = null, bool $lock = false): array
    {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::REVIEW);
        if ($lock) {
            $this->permissions->requirePrivilege($actor, AssessmentPermissions::LOCK);
        }

        $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'));
        if ($assessment === null || ($actor->organizationId !== null && $actor->organizationId !== $assessment->getOrganizationId())) {
            throw new NotFoundException('Assessment not found.');
        }

        $view = $assessment->toView();
        $score = [
            'raw_score' => $adjustedScore ?? $view['initialScore']['raw'],
            'normalized_score' => $view['initialScore']['normalized'],
            'risk_level' => $view['initialScore']['riskLevel'],
            'risk_category' => $view['initialScore']['riskCategory'],
            'algorithm_version' => $view['initialScore']['algorithmVersion'],
        ];
        if ($adjustedScore !== null && trim((string) $adjustmentReason) === '') {
            throw new ValidationException(['adjustmentReason' => 'Adjusted scores require a reason.']);
        }

        $reviewed = $assessment->markReviewed(
            reviewerId: $actor->userId,
            reviewerName: trim($reviewerName),
            reviewerCredentials: $reviewerCredentials,
            reviewerNotes: $reviewerNotes,
            finalScore: $score,
            adjustmentReason: $adjustmentReason,
            lock: $lock,
        );

        $this->tx->transactional(fn() => $this->assessments->update($reviewed));
        $this->audit->record('assessment.reviewed', 'assessment', $reviewed->getUuid(), beforeState: $view, afterState: $reviewed->toView(), actorId: (string) $actor->userId, actorType: 'user');

        return $reviewed->toView();
    }

    /**
     * @return array<string, mixed>
     */
    public function flag(string $assessmentUuid, UserContext $actor, string $reviewerName, string $reviewerNotes, ?string $reviewerCredentials = null): array
    {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::REVIEW);

        $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'));
        if ($assessment === null || ($actor->organizationId !== null && $actor->organizationId !== $assessment->getOrganizationId())) {
            throw new NotFoundException('Assessment not found.');
        }

        $view = $assessment->toView();
        $flagged = $assessment->markFlagged($actor->userId, trim($reviewerName), $reviewerCredentials, $reviewerNotes);

        $this->tx->transactional(fn() => $this->assessments->update($flagged));
        $this->audit->record('assessment.flagged', 'assessment', $flagged->getUuid(), beforeState: $view, afterState: $flagged->toView(), actorId: (string) $actor->userId, actorType: 'user');

        return $flagged->toView();
    }
}
