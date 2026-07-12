<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Presentation;

use WorkEddy\Modules\Assessment\Application\GetAssessmentUseCase;
use WorkEddy\Modules\Assessment\Application\GetComparisonReportUseCase;
use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Platform\Session\UserContext;

final class ComparisonPageData
{
    public function __construct(
        private readonly GetComparisonReportUseCase $comparisonReports,
        private readonly GetAssessmentUseCase $assessments,
        private readonly ICorrectiveActionRepository $correctiveActions,
    ) {}

    /** @return array<string, mixed> */
    public function common(UserContext $ctx, string $title): array
    {
        return [
            'pageTitle' => $title,
            'pagePurpose' => 'Before and after improvement proof',
            'organizationUuid' => $ctx->organizationUuid,
            'can' => [
                'viewComparison' => in_array(AssessmentPermissions::VIEW_COMPARISON, $ctx->privileges, true),
                'generateComparison' => in_array(AssessmentPermissions::GENERATE_COMPARISON, $ctx->privileges, true),
                'lockComparison' => in_array(AssessmentPermissions::LOCK_COMPARISON, $ctx->privileges, true),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function show(UserContext $ctx, string $comparisonUuid): array
    {
        $report = $this->comparisonReports->execute($comparisonUuid, $ctx);
        $baseline = $this->assessments->execute((string) ($report['baselineAssessmentUuid'] ?? ''), $ctx);
        $followUp = $this->assessments->execute((string) ($report['followUpAssessmentUuid'] ?? ''), $ctx);
        $correctiveAction = $this->correctiveAction($report['correctiveActionUuid'] ?? null);

        return [
            'comparison' => $this->comparisonCard($report, $correctiveAction),
            'baseline' => $this->assessmentCard($baseline, 'baseline'),
            'followUp' => $this->assessmentCard($followUp, 'followUp'),
            'correctiveAction' => $correctiveAction,
            'comparisonPdfUrl' => '/api/v1/reporting/comparison/' . rawurlencode((string) ($report['uuid'] ?? $comparisonUuid)) . '/pdf',
        ];
    }

    /** @param array<string, mixed> $assessment @return array<string, mixed> */
    private function assessmentCard(array $assessment, string $kind): array
    {
        $finalScore = is_array($assessment['finalScore'] ?? null) ? $assessment['finalScore'] : [];
        $review = is_array($assessment['review'] ?? null) ? $assessment['review'] : [];
        $screenshotUuid = $this->thumbnailUuid($assessment);

        return [
            'kind' => $kind,
            'uuid' => (string) ($assessment['uuid'] ?? ''),
            'taskUuid' => (string) ($assessment['taskUuid'] ?? ''),
            'createdAt' => $assessment['createdAt'] ?? null,
            'status' => (string) ($assessment['status'] ?? ''),
            'model' => (string) ($assessment['model'] ?? ''),
            'scoreSource' => (string) ($assessment['scoreSource'] ?? ''),
            'score' => $finalScore,
            'riskLevel' => $finalScore['riskLevel'] ?? null,
            'isBaseline' => (bool) ($assessment['isBaseline'] ?? false),
            'isLocked' => (bool) ($assessment['isLocked'] ?? false),
            'reviewerName' => $review['reviewerName'] ?? null,
            'reviewerNotes' => $review['reviewerNotes'] ?? null,
            'adjustmentReason' => $review['adjustmentReason'] ?? null,
            'bodyRegionHeatmap' => is_array($assessment['bodyRegionHeatmap'] ?? null) ? $assessment['bodyRegionHeatmap'] : [],
            'screenshotUrl' => $screenshotUuid !== null ? '/api/v1/storage/files/' . rawurlencode($screenshotUuid) . '/view' : null,
            'screenshotAlt' => $kind === 'baseline' ? 'Baseline screenshot' : 'Follow-up screenshot',
        ];
    }

    /** @param array<string, mixed> $report @return array<string, mixed> */
    private function comparisonCard(array $report, ?array $correctiveAction): array
    {
        $baselineScore = is_array($report['baselineScore'] ?? null) ? $report['baselineScore'] : [];
        $followUpScore = is_array($report['followUpScore'] ?? null) ? $report['followUpScore'] : [];

        return [
            'uuid' => (string) ($report['uuid'] ?? ''),
            'status' => (string) ($report['status'] ?? 'generated'),
            'model' => (string) ($report['model'] ?? ''),
            'direction' => (string) ($report['direction'] ?? 'unchanged'),
            'generatedAt' => $report['generatedAt'] ?? null,
            'lockedAt' => $report['lockedAt'] ?? null,
            'riskReductionPercent' => (float) ($report['riskReductionPercent'] ?? 0.0),
            'scoreDiff' => is_array($report['scoreDiff'] ?? null) ? $report['scoreDiff'] : [],
            'originalTaskScore' => $baselineScore['raw'] ?? null,
            'correctedTaskScore' => $followUpScore['raw'] ?? null,
            'riskLevelBefore' => $baselineScore['riskLevel'] ?? null,
            'riskLevelAfter' => $followUpScore['riskLevel'] ?? null,
            'bodyRegionsImproved' => is_array($report['bodyRegionsImproved'] ?? null) ? $report['bodyRegionsImproved'] : [],
            'bodyRegionsWorsened' => is_array($report['bodyRegionsWorsened'] ?? null) ? $report['bodyRegionsWorsened'] : [],
            'correctiveAction' => $correctiveAction,
            'evidenceChain' => is_array($report['evidenceChain'] ?? null) ? $report['evidenceChain'] : [],
        ];
    }

    /** @return array<string, mixed>|null */
    private function correctiveAction(?string $actionUuid): ?array
    {
        $uuid = is_string($actionUuid) ? trim($actionUuid) : '';
        if ($uuid === '') {
            return null;
        }

        $action = $this->correctiveActions->findActionByUuid($uuid);
        if ($action === null) {
            return null;
        }

        return [
            'uuid' => $action->uuid,
            'title' => $action->title,
            'status' => $action->status,
            'description' => $action->description,
            'reason' => $action->reason,
            'controlType' => $action->controlType,
            'hierarchyLevel' => $action->hierarchyLevel,
            'priority' => $action->priority,
            'dueDate' => $action->dueDate,
            'followUpAssessmentDueDate' => $action->followUpAssessmentDueDate,
            'completedAt' => $action->completedAt,
            'verifiedAt' => $action->verifiedAt,
            'evidence' => $this->correctiveActions->listEvidenceByActionUuid($uuid),
            'history' => $this->correctiveActions->listStatusHistoryByActionUuid($uuid),
        ];
    }

    /** @param array<string, mixed> $assessment */
    private function thumbnailUuid(array $assessment): ?string
    {
        $assets = is_array($assessment['videoAssets'] ?? null) ? $assessment['videoAssets'] : [];
        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            if ((string) ($asset['assetType'] ?? '') !== 'thumbnail') {
                continue;
            }

            $uuid = trim((string) ($asset['storageFileUuid'] ?? ''));
            if ($uuid !== '') {
                return $uuid;
            }
        }

        return null;
    }
}
