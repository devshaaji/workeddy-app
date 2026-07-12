<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class UpdateAssessmentUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly AssessmentEngine $engine,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
    ) {}

    /**
     * @param array<string, mixed>|null $metrics
     * @param list<string>|null $riskFactors
     * @param list<array<string, mixed>>|null $bodyRegions
     * @return array<string, mixed>
     */
    public function execute(string $assessmentUuid, UserContext $actor, ?array $metrics = null, ?array $riskFactors = null, ?array $bodyRegions = null): array
    {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::UPDATE);
        $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'));
        if ($assessment === null || ($actor->organizationId !== null && $actor->organizationId !== $assessment->getOrganizationId())) {
            throw new NotFoundException('Assessment not found.');
        }
        $assessment->assertMutable();
        if (!in_array($assessment->getStatus(), ['draft', 'flagged'], true)) {
            throw new ValidationException(['status' => 'Only draft or flagged assessments can be updated.']);
        }

        $before = $assessment->toView();
        $nextMetrics = $metrics ?? $assessment->getMetrics();
        $score = $metrics !== null ? $this->engine->assess($assessment->getModel(), $nextMetrics) : $assessment->getInitialScoreData();
        $updated = Assessment::reconstitute(
            id: $assessment->getId(),
            uuid: $assessment->getUuid(),
            organizationId: $assessment->getOrganizationId(),
            organizationUuid: $assessment->getOrganizationUuid(),
            taskId: $assessment->getTaskId(),
            taskUuid: $assessment->getTaskUuid(),
            model: $assessment->getModel(),
            metrics: $nextMetrics,
            initialScore: $score,
            riskFactors: $riskFactors !== null ? $this->normalizeRiskFactors($riskFactors) : $assessment->getRiskFactors(),
            bodyRegions: $bodyRegions !== null ? $this->normalizeBodyRegions($bodyRegions) : $assessment->getBodyRegions(),
            createdBy: $assessment->getCreatedBy(),
            status: $assessment->getStatus(),
            scoreSource: 'manual',
            finalScore: null,
            reviewerId: $assessment->getReviewerId(),
            reviewerName: $assessment->getReviewerName(),
            reviewerCredentials: $assessment->getReviewerCredentials(),
            reviewerNotes: $assessment->getReviewerNotes(),
            adjustmentReason: $assessment->getAdjustmentReason(),
            videos: $assessment->getVideos(),
            createdAt: $assessment->getCreatedAt(),
        );

        $this->tx->transactional(fn() => $this->assessments->update($updated));
        $this->audit->record('assessment.updated', 'assessment', $updated->getUuid(), beforeState: $before, afterState: $updated->toView(), actorId: (string) $actor->userId, actorType: 'user');

        return $updated->toView();
    }

    /**
     * @param list<string> $riskFactors
     * @return list<string>
     */
    private function normalizeRiskFactors(array $riskFactors): array
    {
        return array_values(array_filter(array_map(static fn(string $value): string => trim($value), $riskFactors), static fn(string $value): bool => $value !== ''));
    }

    /**
     * @param list<array<string, mixed>> $bodyRegions
     * @return list<array<string, mixed>>
     */
    private function normalizeBodyRegions(array $bodyRegions): array
    {
        return array_map(static function (array $region): array {
            $name = trim((string) ($region['region'] ?? ''));
            if ($name === '') {
                throw new ValidationException(['bodyRegions' => 'Body region is required.']);
            }

            return [
                'region' => $name,
                'side' => trim((string) ($region['side'] ?? 'front')),
                'intensity' => max(0, min(5, (int) ($region['intensity'] ?? 0))),
            ];
        }, $bodyRegions);
    }
}
