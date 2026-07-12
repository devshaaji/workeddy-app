<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Presentation;

use WorkEddy\Modules\Assessment\Application\AttachAssessmentVideoUseCase;
use WorkEddy\Modules\Assessment\Application\CreateManualAssessmentUseCase;
use WorkEddy\Modules\Assessment\Application\CreateVideoAssessmentForProcessingUseCase;
use WorkEddy\Modules\Assessment\Application\GenerateComparisonReportUseCase;
use WorkEddy\Modules\Assessment\Application\GetAssessmentUseCase;
use WorkEddy\Modules\Assessment\Application\GetComparisonReportUseCase;
use WorkEddy\Modules\Assessment\Application\ListAssessmentsUseCase;
use WorkEddy\Modules\Assessment\Application\ListComparisonReportsUseCase;
use WorkEddy\Modules\Assessment\Application\LockComparisonReportUseCase;
use WorkEddy\Modules\Assessment\Application\MarkAssessmentBaselineUseCase;
use WorkEddy\Modules\Assessment\Application\ReviewAssessmentUseCase;
use WorkEddy\Modules\Assessment\Application\ListValidationReviewsUseCase;
use WorkEddy\Modules\Assessment\Application\SubmitAssessmentForReviewUseCase;
use WorkEddy\Modules\Assessment\Application\SubmitValidationReviewUseCase;
use WorkEddy\Modules\Assessment\Application\UpdateAssessmentUseCase;
use WorkEddy\Modules\Assessment\Application\UploadAssessmentVideoForProcessingUseCase;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;

final class AssessmentController
{
    public function __construct(
        private readonly CreateManualAssessmentUseCase $createManual,
        private readonly CreateVideoAssessmentForProcessingUseCase $createVideoAssessment,
        private readonly ListAssessmentsUseCase $listAssessments,
        private readonly GetAssessmentUseCase $getAssessment,
        private readonly SubmitAssessmentForReviewUseCase $submitAssessment,
        private readonly AttachAssessmentVideoUseCase $attachVideo,
        private readonly UploadAssessmentVideoForProcessingUseCase $uploadVideoForProcessing,
        private readonly ReviewAssessmentUseCase $reviewAssessment,
        private readonly ?SubmitValidationReviewUseCase $submitValidationReview = null,
        private readonly ?ListValidationReviewsUseCase $listValidationReviews = null,
        private readonly ISessionService $session,
        private readonly ?GenerateComparisonReportUseCase $generateComparison = null,
        private readonly ?GetComparisonReportUseCase $getComparison = null,
        private readonly ?ListComparisonReportsUseCase $listComparisons = null,
        private readonly ?LockComparisonReportUseCase $lockComparison = null,
        private readonly ?UpdateAssessmentUseCase $updateAssessment = null,
        private readonly ?MarkAssessmentBaselineUseCase $markBaseline = null,
    ) {}

    public function list(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->listAssessments->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            actor: $this->requireContext(),
            limit: max(1, min(100, (int) ($request->query('limit') ?? 50))),
            offset: max(0, (int) ($request->query('offset') ?? 0)),
            status: isset($request->query['status']) ? (string) $request->query['status'] : null,
        )]);
    }

    public function reviewerQueue(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->listAssessments->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            actor: $this->requireContext(),
            limit: max(1, min(100, (int) ($request->query('limit') ?? 50))),
            offset: max(0, (int) ($request->query('offset') ?? 0)),
            status: 'pending_review',
        )]);
    }

    public function get(Request $request): Response
    {
        $data = $this->getAssessment->execute(
            assessmentUuid: (string) ($request->routeParam('assessmentId') ?? ''),
            actor: $this->requireContext(),
        );

        $organizationUuid = (string) ($request->routeParam('id') ?? '');
        if ($organizationUuid !== '' && $organizationUuid !== ($data['organizationUuid'] ?? null)) {
            throw new \WorkEddy\Shared\Exceptions\NotFoundException('Assessment not found.');
        }

        return Response::json(['status' => 'ok', 'data' => $data]);
    }

    public function createManual(Request $request): Response
    {
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->createManual->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            taskUuid: (string) ($body['taskUuid'] ?? $body['task_uuid'] ?? ''),
            model: (string) ($body['model'] ?? ''),
            metrics: is_array($body['metrics'] ?? null) ? $body['metrics'] : [],
            actor: $this->requireContext(),
            riskFactors: is_array($body['riskFactors'] ?? null) ? $body['riskFactors'] : (is_array($body['risk_factors'] ?? null) ? $body['risk_factors'] : []),
            bodyRegions: is_array($body['bodyRegions'] ?? null) ? $body['bodyRegions'] : (is_array($body['body_regions'] ?? null) ? $body['body_regions'] : []),
            submitForReview: !in_array((string) ($body['submitMode'] ?? $body['submit_mode'] ?? 'submit'), ['draft', 'save_draft'], true),
        )], 201);
    }

    public function createVideo(Request $request): Response
    {
        $body = $this->requestData($request);
        $file = $request->files['file'] ?? $this->firstFile($request->files);
        if (!is_array($file)) {
            throw new \WorkEddy\Shared\Exceptions\ValidationException(['file' => 'Video file is required.']);
        }

        return Response::json(['status' => 'ok', 'data' => $this->createVideoAssessment->execute(
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            taskUuid: (string) ($body['taskUuid'] ?? $body['task_uuid'] ?? ''),
            actor: $this->requireContext(),
            file: $file,
            durationSeconds: (int) ($body['durationSeconds'] ?? $body['duration_seconds'] ?? 0),
            consentTextVersion: (string) ($body['consentTextVersion'] ?? $body['consent_text_version'] ?? ''),
            acceptedNotice: (bool) ($body['acceptedNotice'] ?? $body['accepted_notice'] ?? false),
            faceBlurRequested: (bool) ($body['faceBlurRequested'] ?? $body['face_blur_requested'] ?? true),
            planCode: isset($body['planCode']) || isset($body['plan_code']) ? (string) ($body['planCode'] ?? $body['plan_code']) : null,
            ipAddress: $request->getClientIp(),
            userAgent: $request->header('user-agent'),
        )], 201);
    }

    public function update(Request $request): Response
    {
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->requireUpdateAssessment()->execute(
            assessmentUuid: (string) ($request->routeParam('assessmentId') ?? ''),
            actor: $this->requireContext(),
            metrics: is_array($body['metrics'] ?? null) ? $body['metrics'] : null,
            riskFactors: is_array($body['riskFactors'] ?? null) ? $body['riskFactors'] : (is_array($body['risk_factors'] ?? null) ? $body['risk_factors'] : null),
            bodyRegions: is_array($body['bodyRegions'] ?? null) ? $body['bodyRegions'] : (is_array($body['body_regions'] ?? null) ? $body['body_regions'] : null),
        )]);
    }

    public function markBaseline(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->requireMarkBaseline()->execute(
            assessmentUuid: (string) ($request->routeParam('assessmentId') ?? ''),
            actor: $this->requireContext(),
        )]);
    }

    public function submit(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->submitAssessment->execute(
            assessmentUuid: (string) ($request->routeParam('assessmentId') ?? ''),
            actor: $this->requireContext(),
        )]);
    }

    public function attachVideo(Request $request): Response
    {
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->attachVideo->execute(
            assessmentUuid: (string) ($request->routeParam('assessmentId') ?? ''),
            actor: $this->requireContext(),
            storageFileUuid: (string) ($body['storageFileUuid'] ?? $body['storage_file_uuid'] ?? ''),
            originalFilename: (string) ($body['originalFilename'] ?? $body['original_filename'] ?? ''),
            mimeType: (string) ($body['mimeType'] ?? $body['mime_type'] ?? ''),
            sizeBytes: (int) ($body['sizeBytes'] ?? $body['size_bytes'] ?? 0),
            durationSeconds: (int) ($body['durationSeconds'] ?? $body['duration_seconds'] ?? 0),
            consentTextVersion: (string) ($body['consentTextVersion'] ?? $body['consent_text_version'] ?? ''),
            faceBlurRequested: (bool) ($body['faceBlurRequested'] ?? $body['face_blur_requested'] ?? false),
        )], 201);
    }

    public function uploadVideoForProcessing(Request $request): Response
    {
        $body = $this->requestData($request);
        $file = $request->files['file'] ?? $this->firstFile($request->files);
        if (!is_array($file)) {
            throw new \WorkEddy\Shared\Exceptions\ValidationException(['file' => 'Video file is required.']);
        }

        return Response::json(['status' => 'ok', 'data' => $this->uploadVideoForProcessing->execute(
            assessmentUuid: (string) ($request->routeParam('assessmentId') ?? ''),
            organizationUuid: (string) ($body['organizationUuid'] ?? $body['organization_uuid'] ?? ''),
            actor: $this->requireContext(),
            file: $file,
            durationSeconds: (int) ($body['durationSeconds'] ?? $body['duration_seconds'] ?? 0),
            consentTextVersion: (string) ($body['consentTextVersion'] ?? $body['consent_text_version'] ?? ''),
            acceptedNotice: (bool) ($body['acceptedNotice'] ?? $body['accepted_notice'] ?? false),
            faceBlurRequested: (bool) ($body['faceBlurRequested'] ?? $body['face_blur_requested'] ?? true),
            planCode: isset($body['planCode']) || isset($body['plan_code']) ? (string) ($body['planCode'] ?? $body['plan_code']) : null,
            ipAddress: $request->getClientIp(),
            userAgent: $request->header('user-agent'),
        )], 201);
    }

    public function approve(Request $request): Response
    {
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->reviewAssessment->approve(
            assessmentUuid: (string) ($request->routeParam('assessmentId') ?? ''),
            actor: $this->requireContext(),
            reviewerName: (string) ($body['reviewerName'] ?? $body['reviewer_name'] ?? ''),
            reviewerCredentials: isset($body['reviewerCredentials']) || isset($body['reviewer_credentials']) ? (string) ($body['reviewerCredentials'] ?? $body['reviewer_credentials']) : null,
            reviewerNotes: isset($body['reviewerNotes']) || isset($body['reviewer_notes']) ? (string) ($body['reviewerNotes'] ?? $body['reviewer_notes']) : null,
            adjustedScore: isset($body['adjustedScore']) || isset($body['adjusted_score']) ? (float) ($body['adjustedScore'] ?? $body['adjusted_score']) : null,
            adjustmentReason: isset($body['adjustmentReason']) || isset($body['adjustment_reason']) ? (string) ($body['adjustmentReason'] ?? $body['adjustment_reason']) : null,
            lock: (bool) ($body['lock'] ?? false),
        )]);
    }

    public function listComparisons(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->requireListComparisons()->execute(
            actor: $this->requireContext(),
            filters: [
                'baselineAssessmentUuid' => $request->query['baselineAssessmentUuid'] ?? $request->query['baseline_assessment_uuid'] ?? null,
                'followUpAssessmentUuid' => $request->query['followUpAssessmentUuid'] ?? $request->query['follow_up_assessment_uuid'] ?? null,
                'correctiveActionUuid' => $request->query['correctiveActionUuid'] ?? $request->query['corrective_action_uuid'] ?? null,
                'status' => $request->query['status'] ?? null,
            ],
            limit: max(1, min(100, (int) ($request->query('limit') ?? 50))),
            offset: max(0, (int) ($request->query('offset') ?? 0)),
        )]);
    }

    public function generateComparison(Request $request): Response
    {
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->requireGenerateComparison()->execute(
            baselineAssessmentUuid: (string) ($body['baselineAssessmentUuid'] ?? $body['baseline_assessment_uuid'] ?? ''),
            followUpAssessmentUuid: (string) ($body['followUpAssessmentUuid'] ?? $body['follow_up_assessment_uuid'] ?? ''),
            actor: $this->requireContext(),
            correctiveActionUuid: isset($body['correctiveActionUuid']) || isset($body['corrective_action_uuid'])
                ? (string) ($body['correctiveActionUuid'] ?? $body['corrective_action_uuid'])
                : null,
        )], 201);
    }

    public function getComparison(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->requireGetComparison()->execute(
            comparisonReportUuid: (string) ($request->routeParam('comparisonId') ?? ''),
            actor: $this->requireContext(),
        )]);
    }

    public function lockComparison(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->requireLockComparison()->execute(
            comparisonReportUuid: (string) ($request->routeParam('comparisonId') ?? ''),
            actor: $this->requireContext(),
        )]);
    }

    public function flag(Request $request): Response
    {
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->reviewAssessment->flag(
            assessmentUuid: (string) ($request->routeParam('assessmentId') ?? ''),
            actor: $this->requireContext(),
            reviewerName: (string) ($body['reviewerName'] ?? $body['reviewer_name'] ?? ''),
            reviewerNotes: (string) ($body['reviewerNotes'] ?? $body['reviewer_notes'] ?? ''),
            reviewerCredentials: isset($body['reviewerCredentials']) || isset($body['reviewer_credentials']) ? (string) ($body['reviewerCredentials'] ?? $body['reviewer_credentials']) : null,
        )]);
    }

    public function listValidationReviews(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->requireListValidationReviews()->execute(
            assessmentUuid: (string) ($request->routeParam('assessmentId') ?? ''),
            actor: $this->requireContext(),
            finalOnly: in_array((string) ($request->query('finalOnly') ?? 'false'), ['1', 'true'], true),
        )]);
    }

    public function submitValidationReview(Request $request): Response
    {
        $body = $this->requestData($request);

        return Response::json(['status' => 'ok', 'data' => $this->requireSubmitValidationReview()->execute(
            assessmentUuid: (string) ($request->routeParam('assessmentId') ?? ''),
            actor: $this->requireContext(),
            reviewerName: (string) ($body['reviewerName'] ?? $body['reviewer_name'] ?? ''),
            reviewerCredentials: isset($body['reviewerCredentials']) || isset($body['reviewer_credentials']) ? (string) ($body['reviewerCredentials'] ?? $body['reviewer_credentials']) : null,
            score: is_array($body['score'] ?? null) ? $body['score'] : [],
            riskLevel: (string) ($body['riskLevel'] ?? $body['risk_level'] ?? ''),
            bodyRegions: is_array($body['bodyRegions'] ?? null) ? $body['bodyRegions'] : (is_array($body['body_regions'] ?? null) ? $body['body_regions'] : []),
            riskFactors: is_array($body['riskFactors'] ?? null) ? $body['riskFactors'] : (is_array($body['risk_factors'] ?? null) ? $body['risk_factors'] : []),
            notes: isset($body['notes']) ? (string) $body['notes'] : null,
            reviewRound: (int) ($body['reviewRound'] ?? $body['review_round'] ?? 1),
            isPrimary: (bool) ($body['isPrimary'] ?? $body['is_primary'] ?? false),
            isFinal: (bool) ($body['isFinal'] ?? $body['is_final'] ?? true),
        )], 201);
    }

    /** @return array<string, mixed> */
    private function requestData(Request $request): array
    {
        return array_replace($request->body, $request->json);
    }

    /** @param array<string, mixed> $files */
    private function firstFile(array $files): ?array
    {
        foreach ($files as $file) {
            if (is_array($file)) {
                return $file;
            }
        }

        return null;
    }

    private function requireContext(): UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }

    private function requireUpdateAssessment(): UpdateAssessmentUseCase
    {
        return $this->updateAssessment ?? throw new \RuntimeException('Update assessment use case is not configured.');
    }

    private function requireMarkBaseline(): MarkAssessmentBaselineUseCase
    {
        return $this->markBaseline ?? throw new \RuntimeException('Mark assessment baseline use case is not configured.');
    }

    private function requireGenerateComparison(): GenerateComparisonReportUseCase
    {
        return $this->generateComparison ?? throw new \RuntimeException('Generate comparison use case is not configured.');
    }

    private function requireGetComparison(): GetComparisonReportUseCase
    {
        return $this->getComparison ?? throw new \RuntimeException('Get comparison use case is not configured.');
    }

    private function requireListComparisons(): ListComparisonReportsUseCase
    {
        return $this->listComparisons ?? throw new \RuntimeException('List comparisons use case is not configured.');
    }

    private function requireLockComparison(): LockComparisonReportUseCase
    {
        return $this->lockComparison ?? throw new \RuntimeException('Lock comparison use case is not configured.');
    }

    private function requireSubmitValidationReview(): SubmitValidationReviewUseCase
    {
        return $this->submitValidationReview ?? throw new \RuntimeException('Submit validation review use case is not configured.');
    }

    private function requireListValidationReviews(): ListValidationReviewsUseCase
    {
        return $this->listValidationReviews ?? throw new \RuntimeException('List validation reviews use case is not configured.');
    }
}
