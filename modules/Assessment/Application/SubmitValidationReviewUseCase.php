<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Assessment\Domain\Contracts\IValidationReviewRepository;
use WorkEddy\Modules\Assessment\Domain\ValidationReview;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class SubmitValidationReviewUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly IValidationReviewRepository $reviews,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
    ) {}

    /**
     * @param array<string, mixed> $score
     * @param list<string> $bodyRegions
     * @param list<string> $riskFactors
     * @return array<string, mixed>
     */
    public function execute(
        string $assessmentUuid,
        UserContext $actor,
        string $reviewerName,
        ?string $reviewerCredentials,
        array $score,
        string $riskLevel,
        array $bodyRegions,
        array $riskFactors,
        ?string $notes = null,
        int $reviewRound = 1,
        bool $isPrimary = false,
        bool $isFinal = true,
    ): array {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::REVIEW);
        $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentId'));
        if ($assessment === null || $assessment->getOrganizationId() !== $actor->organizationId) {
            throw new NotFoundException('Assessment not found.');
        }
        if (!in_array($assessment->getStatus(), ['reviewed', 'locked'], true)) {
            throw new ValidationException(['assessment' => 'Validation reviews require a reviewed or locked assessment.']);
        }

        $review = new ValidationReview(
            id: null,
            uuid: UuidSupport::generate(),
            organizationId: $assessment->getOrganizationId(),
            organizationUuid: $assessment->getOrganizationUuid(),
            assessmentUuid: $assessment->getUuid(),
            assessmentVersion: sha1(json_encode($assessment->toView(), JSON_THROW_ON_ERROR)),
            reviewerUserId: (int) $actor->userId,
            reviewerName: trim($reviewerName),
            reviewerCredentials: $reviewerCredentials !== null ? trim($reviewerCredentials) : null,
            reviewRound: $reviewRound,
            score: $score,
            riskLevel: trim($riskLevel),
            bodyRegions: array_values($bodyRegions),
            riskFactors: array_values($riskFactors),
            notes: $notes !== null ? trim($notes) : null,
            isPrimary: $isPrimary,
            isFinal: $isFinal,
            submittedAt: date('Y-m-d H:i:s'),
        );

        $this->tx->transactional(function () use ($review, $actor): void {
            $this->reviews->create($review);
            $this->audit->record(
                action: 'assessment.validation_review.submitted',
                entityType: 'ValidationReview',
                entityId: $review->uuid,
                afterState: $review->toView(),
                actorId: (string) $actor->userId,
                actorType: 'User',
            );
        });

        return $review->toView();
    }
}
