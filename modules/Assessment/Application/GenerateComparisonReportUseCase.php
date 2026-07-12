<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Application\Services\AssessmentComparisonService;
use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\ComparisonReport;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class GenerateComparisonReportUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly AssessmentComparisonService $comparison,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
        private readonly ?ICorrectiveActionRepository $correctiveActions = null,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $baselineAssessmentUuid, string $followUpAssessmentUuid, UserContext $actor, ?string $correctiveActionUuid = null): array
    {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::GENERATE_COMPARISON);
        $baseline = $this->assessments->findByUuid(UuidSupport::requireValid($baselineAssessmentUuid, 'baselineAssessmentUuid'));
        $followUp = $this->assessments->findByUuid(UuidSupport::requireValid($followUpAssessmentUuid, 'followUpAssessmentUuid'));
        if ($baseline === null || $followUp === null) {
            throw new NotFoundException('Baseline or follow-up assessment not found.');
        }
        if (($actor->organizationId !== null) && ($actor->organizationId !== $baseline->getOrganizationId() || $actor->organizationId !== $followUp->getOrganizationId())) {
            throw new NotFoundException('Assessment not found.');
        }

        $validatedCorrectiveActionUuid = $this->validatedCorrectiveActionUuid($correctiveActionUuid, $baseline->getUuid(), $baseline->getOrganizationId());
        $comparison = $this->comparison->compare($baseline, $followUp);
        $existing = $this->assessments->findComparisonReportByBaselineAndFollowUp($baseline->getUuid(), $followUp->getUuid());
        $report = new ComparisonReport(
            id: $existing?->id,
            uuid: $existing?->uuid ?? UuidSupport::generate(),
            organizationId: $baseline->getOrganizationId(),
            organizationUuid: $baseline->getOrganizationUuid(),
            baselineAssessmentUuid: $baseline->getUuid(),
            followUpAssessmentUuid: $followUp->getUuid(),
            correctiveActionUuid: $validatedCorrectiveActionUuid,
            model: (string) $comparison['model'],
            baselineScore: $comparison['baselineScore'],
            followUpScore: $comparison['followUpScore'],
            scoreDiff: $comparison['scoreDiff'],
            riskReductionPercent: (float) (($comparison['improvementProof']['risk_reduction_percent'] ?? 0.0)),
            direction: (string) ($comparison['improvementProof']['direction'] ?? 'unchanged'),
            bodyRegionsImproved: $comparison['bodyRegionsImproved'],
            bodyRegionsWorsened: $comparison['bodyRegionsWorsened'],
            evidenceChain: $this->evidenceChain($comparison, $baseline->toView(), $followUp->toView(), $validatedCorrectiveActionUuid),
            status: $existing?->status ?? 'generated',
            generatedBy: $actor->userId ?? 0,
            generatedAt: $existing?->generatedAt ?? date('Y-m-d H:i:s'),
            lockedAt: $existing?->lockedAt,
            createdAt: $existing?->createdAt ?? date('Y-m-d H:i:s'),
        );

        $before = $existing?->toView();
        $this->tx->transactional(function () use ($existing, $report): void {
            if ($existing === null) {
                $this->assessments->createComparisonReport($report);
                return;
            }
            $this->assessments->updateComparisonReport($report);
        });

        $result = $report->toView();
        $this->audit->record('comparison_report.generated', 'comparison_report', $report->uuid, beforeState: $before, afterState: $result, actorId: (string) $actor->userId, actorType: 'user');

        return $result;
    }

    private function validatedCorrectiveActionUuid(?string $correctiveActionUuid, string $baselineAssessmentUuid, int $organizationId): ?string
    {
        if ($correctiveActionUuid === null || trim($correctiveActionUuid) === '') {
            return null;
        }

        $uuid = UuidSupport::requireValid($correctiveActionUuid, 'correctiveActionUuid');
        if ($this->correctiveActions === null) {
            throw new ValidationException(['correctiveActionUuid' => 'Corrective action repository is required to link comparison evidence.']);
        }

        $action = $this->correctiveActions->findActionByUuid($uuid);
        if ($action === null || $action->organizationId !== $organizationId || $action->assessmentUuid !== $baselineAssessmentUuid) {
            throw new NotFoundException('Corrective action not found for baseline assessment.');
        }
        if ($action->status !== 'verified') {
            throw new ValidationException(['correctiveActionUuid' => 'Corrective action must be verified before comparison report linkage.']);
        }

        return $uuid;
    }

    /** @param array<string, mixed> $comparison @param array<string, mixed> $baseline @param array<string, mixed> $followUp @return array<string, mixed> */
    private function evidenceChain(array $comparison, array $baseline, array $followUp, ?string $correctiveActionUuid): array
    {
        $chain = [
            'baseline' => [
                'assessmentUuid' => $baseline['uuid'] ?? null,
                'model' => $baseline['model'] ?? null,
                'createdAt' => $baseline['createdAt'] ?? null,
                'status' => $baseline['status'] ?? null,
                'scoreSource' => $baseline['scoreSource'] ?? null,
                'finalScore' => $baseline['finalScore'] ?? null,
                'bodyRegions' => $baseline['bodyRegions'] ?? [],
                'bodyRegionHeatmap' => $baseline['bodyRegionHeatmap'] ?? [],
                'review' => $baseline['review'] ?? null,
            ],
            'followUp' => [
                'assessmentUuid' => $followUp['uuid'] ?? null,
                'model' => $followUp['model'] ?? null,
                'createdAt' => $followUp['createdAt'] ?? null,
                'status' => $followUp['status'] ?? null,
                'scoreSource' => $followUp['scoreSource'] ?? null,
                'finalScore' => $followUp['finalScore'] ?? null,
                'bodyRegions' => $followUp['bodyRegions'] ?? [],
                'bodyRegionHeatmap' => $followUp['bodyRegionHeatmap'] ?? [],
                'review' => $followUp['review'] ?? null,
            ],
            'comparison' => $comparison['improvementProof']['evidence_chain'] ?? [],
        ];

        if ($correctiveActionUuid !== null && $this->correctiveActions !== null) {
            $action = $this->correctiveActions->findActionByUuid($correctiveActionUuid);
            if ($action !== null) {
                $chain['correctiveAction'] = $action->toView();
                $chain['correctiveActionEvidence'] = $this->correctiveActions->listEvidenceByActionUuid($correctiveActionUuid);
                $chain['correctiveActionHistory'] = $this->correctiveActions->listStatusHistoryByActionUuid($correctiveActionUuid);
            }
        }

        return $chain;
    }
}
