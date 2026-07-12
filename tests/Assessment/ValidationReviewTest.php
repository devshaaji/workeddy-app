<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Assessment;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Assessment\Application\GetAssessmentUseCase;
use WorkEddy\Modules\Assessment\Application\ListValidationReviewsUseCase;
use WorkEddy\Modules\Assessment\Application\Services\ValidationAgreementService;
use WorkEddy\Modules\Assessment\Application\SubmitValidationReviewUseCase;
use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\AiScoreOutput;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;
use WorkEddy\Modules\Assessment\Domain\ComparisonReport;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Assessment\Domain\Contracts\IValidationReviewRepository;
use WorkEddy\Modules\Assessment\Domain\ValidationReview;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;

final class ValidationReviewTest extends TestCase
{
    public function test_submit_and_list_validation_reviews_and_compute_agreement(): void
    {
        $assessment = Assessment::create(
            id: 1,
            uuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: [],
            initialScore: ['raw_score' => 8.0, 'risk_level' => 'High'],
            riskFactors: ['forceful_exertion'],
            bodyRegions: [['region' => 'lower_back', 'side' => 'back', 'intensity' => 4]],
            createdBy: 44,
        )->markSubmitted()->markReviewed(
            reviewerId: 44,
            reviewerName: 'Primary Reviewer',
            reviewerCredentials: 'CPE',
            reviewerNotes: 'Ready for validation.',
            finalScore: ['raw_score' => 8.0, 'risk_level' => 'High'],
            adjustmentReason: null,
            lock: true,
        );

        $assessments = new InMemoryValidationAssessmentRepository([$assessment]);
        $reviews = new InMemoryValidationReviewRepository();
        $permissions = new AllowValidationPermissions();
        $tx = new PassthroughValidationTx();
        $audit = new RecordingValidationAudit();
        $submit = new SubmitValidationReviewUseCase($assessments, $reviews, $permissions, $tx, $audit);

        $actorA = new UserContext(userId: 44, organizationId: 3, organizationUuid: '11111111-1111-4111-8111-111111111111', roleType: 'reviewer', privileges: [AssessmentPermissions::REVIEW, AssessmentPermissions::VIEW]);
        $actorB = new UserContext(userId: 45, organizationId: 3, organizationUuid: '11111111-1111-4111-8111-111111111111', roleType: 'reviewer', privileges: [AssessmentPermissions::REVIEW, AssessmentPermissions::VIEW]);

        $first = $submit->execute(
            assessmentUuid: $assessment->getUuid(),
            actor: $actorA,
            reviewerName: 'Dr One',
            reviewerCredentials: 'CPE',
            score: ['raw' => 8.0, 'normalized' => 53.3],
            riskLevel: 'High',
            bodyRegions: ['lower_back'],
            riskFactors: ['forceful_exertion'],
            notes: 'Matches baseline.',
            reviewRound: 1,
            isPrimary: true,
            isFinal: true,
        );

        $second = $submit->execute(
            assessmentUuid: $assessment->getUuid(),
            actor: $actorB,
            reviewerName: 'Dr Two',
            reviewerCredentials: 'PT',
            score: ['raw' => 8.0, 'normalized' => 53.3],
            riskLevel: 'High',
            bodyRegions: ['lower_back'],
            riskFactors: ['forceful_exertion'],
            notes: 'Independent agreement.',
            reviewRound: 1,
            isPrimary: false,
            isFinal: true,
        );

        self::assertSame('High', $first['riskLevel']);
        self::assertTrue($first['isPrimary']);
        self::assertSame('Dr Two', $second['reviewerName']);

        $listed = (new ListValidationReviewsUseCase($assessments, $reviews, $permissions))->execute($assessment->getUuid(), $actorA, true);
        self::assertCount(2, $listed);

        $agreement = (new ValidationAgreementService())->summarize($reviews->findByOrganizationId(3, [], true));
        self::assertSame(1, $agreement['pairCount']);
        self::assertSame(100.0, $agreement['riskLevelAgreementRate']);
        self::assertSame(100.0, $agreement['scoreAgreementRate']);
        self::assertSame(100.0, $agreement['bodyRegionAgreementRate']);
        self::assertSame(['assessment.validation_review.submitted', 'assessment.validation_review.submitted'], array_column($audit->records, 'action'));

        $view = (new GetAssessmentUseCase($assessments, $permissions, $reviews, new ValidationAgreementService()))->execute(
            $assessment->getUuid(),
            $actorA,
        );

        self::assertCount(2, $view['validationReviews']);
        self::assertSame(1, $view['validationAgreement']['pairCount']);
        self::assertSame(100.0, $view['validationAgreement']['overallAgreementRate']);
        self::assertTrue($view['actions']['canSubmitValidationReview']);
    }
}

final class InMemoryValidationAssessmentRepository implements IAssessmentRepository
{
    /** @var array<string, Assessment> */
    private array $items = [];

    /** @param list<Assessment> $items */
    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->items[$item->getUuid()] = $item;
        }
    }

    public function create(Assessment $assessment): int { $this->items[$assessment->getUuid()] = $assessment; return (int) ($assessment->getId() ?? count($this->items)); }
    public function update(Assessment $assessment): void { $this->items[$assessment->getUuid()] = $assessment; }
    public function addVideo(AssessmentVideo $video): int { return 1; }
    public function updateVideoProcessing(AssessmentVideo $video): void {}
    public function saveVideoProcessingResult(array $result): void {}
    public function findReusableVideoProcessingResult(string $videoSha256, string $processingProfileHash): ?array { return null; }
    public function saveAiScoreOutput(AiScoreOutput $output): int { return 1; }
    public function findLatestAiScoreOutput(string $assessmentUuid): ?AiScoreOutput { return null; }
    public function findByUuid(string $uuid): ?Assessment { return $this->items[$uuid] ?? null; }
    public function findById(int $id): ?Assessment { foreach ($this->items as $item) { if ($item->getId() === $id) { return $item; } } return null; }
    public function findAllByOrganizationId(?int $organizationId, int $limit = 50, int $offset = 0): array { return array_slice(array_values(array_filter($this->items, static fn(Assessment $item): bool => $organizationId === null || $item->getOrganizationId() === $organizationId)), $offset, $limit); }
    public function createComparisonReport(ComparisonReport $report): int { return 1; }
    public function updateComparisonReport(ComparisonReport $report): void {}
    public function findComparisonReportByUuid(string $uuid): ?ComparisonReport { return null; }
    public function findComparisonReportByBaselineAndFollowUp(string $baselineAssessmentUuid, string $followUpAssessmentUuid): ?ComparisonReport { return null; }
    public function findComparisonReportsByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array { return []; }
}

final class InMemoryValidationReviewRepository implements IValidationReviewRepository
{
    /** @var array<string, ValidationReview> */
    private array $items = [];
    private int $nextId = 1;

    public function create(ValidationReview $review): int
    {
        $id = $review->id ?? $this->nextId++;
        $this->items[$review->uuid] = new ValidationReview(
            id: $id,
            uuid: $review->uuid,
            organizationId: $review->organizationId,
            organizationUuid: $review->organizationUuid,
            assessmentUuid: $review->assessmentUuid,
            assessmentVersion: $review->assessmentVersion,
            reviewerUserId: $review->reviewerUserId,
            reviewerName: $review->reviewerName,
            reviewerCredentials: $review->reviewerCredentials,
            reviewRound: $review->reviewRound,
            score: $review->score,
            riskLevel: $review->riskLevel,
            bodyRegions: $review->bodyRegions,
            riskFactors: $review->riskFactors,
            notes: $review->notes,
            isPrimary: $review->isPrimary,
            isFinal: $review->isFinal,
            submittedAt: $review->submittedAt,
            createdAt: $review->createdAt,
            updatedAt: $review->updatedAt,
        );

        return $id;
    }

    public function findByUuid(string $uuid): ?ValidationReview
    {
        return $this->items[$uuid] ?? null;
    }

    public function findByAssessmentUuid(string $assessmentUuid, bool $finalOnly = false): array
    {
        return array_values(array_filter($this->items, static function (ValidationReview $review) use ($assessmentUuid, $finalOnly): bool {
            if ($review->assessmentUuid !== $assessmentUuid) {
                return false;
            }

            return !$finalOnly || $review->isFinal;
        }));
    }

    public function findByOrganizationId(int $organizationId, array $filters = [], bool $finalOnly = false, int $limit = 500, int $offset = 0): array
    {
        $items = array_values(array_filter($this->items, static function (ValidationReview $review) use ($organizationId, $filters, $finalOnly): bool {
            if ($review->organizationId !== $organizationId) {
                return false;
            }
            if ($finalOnly && !$review->isFinal) {
                return false;
            }
            if (($filters['assessmentUuid'] ?? null) && $review->assessmentUuid !== $filters['assessmentUuid']) {
                return false;
            }

            return true;
        }));

        return array_slice($items, $offset, $limit);
    }
}

final class AllowValidationPermissions implements IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        if (!in_array($privilege, $ctx->privileges, true)) {
            throw new \RuntimeException('Missing expected privilege in test context: ' . $privilege);
        }
    }
}

final class PassthroughValidationTx implements TransactionManagerInterface
{
    public function transactional(callable $callback): mixed
    {
        return $callback();
    }
}

final class RecordingValidationAudit implements IAuditService
{
    public array $records = [];

    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
        $this->records[] = compact('action', 'entityType', 'entityId', 'beforeState', 'afterState', 'actorId', 'actorType', 'idempotencyKey', 'metadata');
    }
}
