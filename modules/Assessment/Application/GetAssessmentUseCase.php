<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\AiScoreOutput;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Assessment\Domain\Contracts\IValidationReviewRepository;
use WorkEddy\Modules\Assessment\Application\Services\ValidationAgreementService;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Privacy\Authorization\PrivacyPermissions;
use WorkEddy\Modules\Reporting\Authorization\ReportingPermissions;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\WrongScopeException;
use WorkEddy\Shared\Support\UuidSupport;

final class GetAssessmentUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly IPermissionService $permissions,
        private readonly ?IValidationReviewRepository $validationReviews = null,
        private readonly ?ValidationAgreementService $validationAgreement = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $assessmentUuid, UserContext $actor): array
    {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::VIEW);

        $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'));
        if ($assessment === null) {
            throw new NotFoundException('Assessment not found.');
        }
        if ($actor->organizationId !== null && $actor->organizationId !== $assessment->getOrganizationId()) {
            throw new WrongScopeException('This assessment belongs to a different organization scope.');
        }

        $view = $assessment->toView();
        $canReview = in_array(AssessmentPermissions::REVIEW, $actor->privileges, true);
        $canLock = in_array(AssessmentPermissions::LOCK, $actor->privileges, true);
        $reviewState = $assessment->getStatus() === 'pending_review';

        $view['actions'] = [
            'canEdit' => $assessment->isMutable() && in_array(AssessmentPermissions::UPDATE, $actor->privileges, true),
            'canReview' => $canReview && $reviewState,
            'canApprove' => $canReview && $reviewState,
            'canFlag' => $canReview && $reviewState,
            'canMarkBaseline' => in_array(AssessmentPermissions::UPDATE, $actor->privileges, true)
                && $canLock
                && !$assessment->isBaseline()
                && in_array($assessment->getStatus(), ['reviewed', 'locked'], true),
            'canGenerateComparison' => in_array(AssessmentPermissions::GENERATE_COMPARISON, $actor->privileges, true)
                && in_array($assessment->getStatus(), ['reviewed', 'locked'], true),
        ];
        $view['statusRail'] = $this->statusRail($view);
        $view['videoAssets'] = $this->normalizeVideoAssets($view, $actor);
        $view['aiAssistance'] = $this->aiAssistance($assessment->getUuid());
        $view['validationReviews'] = $this->validationReviews($assessment->getUuid());
        $view['validationAgreement'] = $this->validationAgreement($view['validationReviews']);
        $view['reporting'] = $this->reportingLinks($assessment->getUuid(), $assessment->getOrganizationId(), $actor);
        $view['actions']['canRequestSignedAccess'] = in_array(PrivacyPermissions::VIDEO_ACCESS, $actor->privileges, true);
        $view['actions']['canViewAssetAudit'] = in_array(PrivacyPermissions::AUDIT_VIEW, $actor->privileges, true);
        $view['actions']['canDownloadReport'] = in_array(ReportingPermissions::VIEW, $actor->privileges, true);
        $view['actions']['canSubmitValidationReview'] = in_array(AssessmentPermissions::REVIEW, $actor->privileges, true)
            && in_array($assessment->getStatus(), ['reviewed', 'locked'], true);

        return $view;
    }

    /** @param array<string, mixed> $view @return array<string, string|null> */
    private function statusRail(array $view): array
    {
        $videos = is_array($view['videos'] ?? null) ? $view['videos'] : [];
        $processingStates = array_values(array_filter(array_map(static fn(array $video): string => (string) ($video['processingStatus'] ?? 'pending'), $videos)));
        $consentCaptured = $videos !== [] && count(array_filter($videos, static fn(array $video): bool => trim((string) ($video['consentTextVersion'] ?? '')) !== '')) === count($videos);
        $anyBlurred = count(array_filter($videos, static fn(array $video): bool => !empty($video['blurredStorageFileUuid']) || !empty($video['facesBlurred']))) > 0;
        $anyBlurRequested = count(array_filter($videos, static fn(array $video): bool => !empty($video['faceBlurRequested']))) > 0;
        $processing = $processingStates === [] ? 'pending' : (in_array('processing', $processingStates, true) ? 'processing' : (in_array('failed', $processingStates, true) ? 'failed' : (in_array('completed', $processingStates, true) ? 'completed' : $processingStates[0])));
        $reportReady = in_array((string) ($view['status'] ?? ''), ['reviewed', 'locked'], true) ? 'ready' : 'pending_review';

        return [
            'consentStatus' => $consentCaptured ? 'captured' : 'missing',
            'processingStatus' => $processing,
            'blurStatus' => $anyBlurred ? 'blurred' : ($anyBlurRequested ? 'requested' : 'not_requested'),
            'retentionStatus' => 'assessment_bound',
            'reviewerReportReadiness' => $reportReady,
        ];
    }

    /** @param array<string, mixed> $view @return list<array<string, mixed>> */
    private function normalizeVideoAssets(array $view, UserContext $actor): array
    {
        $videos = is_array($view['videos'] ?? null) ? $view['videos'] : [];
        $canRequest = in_array(PrivacyPermissions::VIDEO_ACCESS, $actor->privileges, true);
        $canAudit = in_array(PrivacyPermissions::AUDIT_VIEW, $actor->privileges, true);
        $assets = [];

        foreach ($videos as $video) {
            $base = [
                'label' => (string) ($video['originalFilename'] ?? 'Video evidence'),
                'mimeType' => (string) ($video['mimeType'] ?? 'application/octet-stream'),
                'processingStatus' => (string) ($video['processingStatus'] ?? 'pending'),
                'processingConfidence' => $video['processingConfidence'] ?? null,
                'consentTextVersion' => (string) ($video['consentTextVersion'] ?? ''),
                'faceBlurRequested' => (bool) ($video['faceBlurRequested'] ?? false),
                'facesBlurred' => (bool) ($video['facesBlurred'] ?? false),
                'createdAt' => $video['createdAt'] ?? null,
                'processedAt' => $video['processingCompletedAt'] ?? null,
                'retentionExpiresAt' => null,
                'processingError' => $video['processingError'] ?? null,
                'actions' => [
                    'canView' => $canRequest,
                    'canRequestSignedAccess' => $canRequest,
                    'canViewOriginal' => $canRequest,
                    'canViewBlurred' => $canRequest,
                    'canViewPoseOutput' => $canRequest,
                    'canViewAssetAudit' => $canAudit,
                ],
            ];

            $assets[] = $base + [
                'assetType' => 'original_video',
                'kind' => 'video',
                'storageFileUuid' => (string) ($video['storageFileUuid'] ?? ''),
                'sourceVideoUuid' => (string) ($video['uuid'] ?? ''),
            ];

            if (trim((string) ($video['blurredStorageFileUuid'] ?? '')) !== '') {
                $assets[] = $base + [
                    'assetType' => 'blurred_video',
                    'label' => 'Blurred video',
                    'kind' => 'video',
                    'storageFileUuid' => (string) $video['blurredStorageFileUuid'],
                    'sourceVideoUuid' => (string) ($video['uuid'] ?? ''),
                ];
            }

            if (trim((string) ($video['thumbnailStorageFileUuid'] ?? '')) !== '') {
                $assets[] = $base + [
                    'assetType' => 'thumbnail',
                    'label' => 'Thumbnail',
                    'kind' => 'image',
                    'storageFileUuid' => (string) $video['thumbnailStorageFileUuid'],
                    'sourceVideoUuid' => (string) ($video['uuid'] ?? ''),
                ];
            }

            if (trim((string) ($video['poseVideoStorageFileUuid'] ?? '')) !== '') {
                $assets[] = $base + [
                    'assetType' => 'pose_video',
                    'label' => 'Pose overlay video',
                    'kind' => 'video',
                    'storageFileUuid' => (string) $video['poseVideoStorageFileUuid'],
                    'sourceVideoUuid' => (string) ($video['uuid'] ?? ''),
                ];
            }
        }

        return $assets;
    }

    /**
     * @return array<string, mixed>
     */
    private function aiAssistance(string $assessmentUuid): array
    {
        $output = $this->assessments->findLatestAiScoreOutput($assessmentUuid);
        if ($output === null) {
            return [
                'available' => false,
                'advisoryOnly' => true,
                'requiresReviewerConfirmation' => true,
                'message' => 'No AI estimate is stored for this assessment yet.',
            ];
        }

        $flags = $this->normalizeAiFlags($output);
        $confidence = $output->confidence;

        return [
            'available' => true,
            'advisoryOnly' => true,
            'requiresReviewerConfirmation' => true,
            'message' => 'AI output is evidence support only and cannot publish a final score.',
            'scoreSource' => $output->scoreSource,
            'modelVersion' => $output->modelVersion,
            'confidence' => $confidence,
            'confidenceBand' => $this->confidenceBand($confidence),
            'score' => $this->formatAiScore($output->score),
            'flags' => $flags,
            'timelinePreview' => array_slice($output->timeline, 0, 8),
            'metadata' => $output->metadata,
            'createdAt' => $output->createdAt,
            'createdByWorker' => $output->createdByWorker,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function normalizeAiFlags(AiScoreOutput $output): array
    {
        $flags = [];
        foreach ($output->flags as $key => $value) {
            $flags[(string) $key] = (bool) $value;
        }

        return $flags;
    }

    private function confidenceBand(?float $confidence): string
    {
        if ($confidence === null) {
            return 'unknown';
        }
        if ($confidence < 0.70) {
            return 'low';
        }
        if ($confidence < 0.85) {
            return 'medium';
        }

        return 'high';
    }

    /**
     * @param array<string, mixed> $score
     * @return array<string, mixed>
     */
    private function formatAiScore(array $score): array
    {
        return [
            'raw' => isset($score['raw_score']) ? (float) $score['raw_score'] : (float) ($score['score'] ?? 0),
            'normalized' => isset($score['normalized_score']) ? (float) $score['normalized_score'] : null,
            'riskLevel' => $score['risk_level'] ?? null,
            'riskCategory' => $score['risk_category'] ?? null,
            'algorithmVersion' => $score['algorithm_version'] ?? null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function validationReviews(string $assessmentUuid): array
    {
        if ($this->validationReviews === null) {
            return [];
        }

        return array_map(
            static fn($review): array => $review->toView(),
            $this->validationReviews->findByAssessmentUuid($assessmentUuid, true),
        );
    }

    /**
     * @param list<array<string, mixed>> $reviews
     * @return array<string, mixed>
     */
    private function validationAgreement(array $reviews): array
    {
        if ($this->validationAgreement === null || $this->validationReviews === null || $reviews === []) {
            return [
                'assessmentsReviewed' => 0,
                'pairCount' => 0,
                'overallAgreementRate' => 0.0,
                'riskLevelAgreementRate' => 0.0,
                'scoreAgreementRate' => 0.0,
                'bodyRegionAgreementRate' => 0.0,
                'riskFactorAgreementRate' => 0.0,
            ];
        }

        $domainReviews = $this->validationReviews->findByAssessmentUuid((string) ($reviews[0]['assessmentUuid'] ?? ''), true);

        return $this->validationAgreement->summarize($domainReviews);
    }

    /** @return array<string, mixed> */
    private function reportingLinks(string $assessmentUuid, int $organizationId, UserContext $actor): array
    {
        $canDownload = in_array(ReportingPermissions::VIEW, $actor->privileges, true);
        $reports = [];

        if ($canDownload) {
            $reports[] = [
                'reportType' => 'assessment_report',
                'label' => 'Assessment report',
                'url' => '/api/v1/reporting/assessment/' . rawurlencode($assessmentUuid) . '/pdf',
            ];

            foreach ($this->assessments->findComparisonReportsByOrganizationId($organizationId, [], 100, 0) as $report) {
                if ($report->baselineAssessmentUuid !== $assessmentUuid && $report->followUpAssessmentUuid !== $assessmentUuid) {
                    continue;
                }

                $reports[] = [
                    'reportType' => 'comparison_report',
                    'label' => 'Comparison report',
                    'url' => '/api/v1/reporting/comparison/' . rawurlencode($report->uuid) . '/pdf',
                ];

                if ($report->correctiveActionUuid !== null && $report->correctiveActionUuid !== '') {
                    $reports[] = [
                        'reportType' => 'corrective_action_report',
                        'label' => 'Corrective-action report',
                        'url' => '/api/v1/reporting/corrective-action/' . rawurlencode($report->correctiveActionUuid) . '/pdf',
                    ];
                }

                break;
            }
        }

        return [
            'reports' => $reports,
            'actions' => [
                'canDownloadReport' => $canDownload,
            ],
        ];
    }
}
