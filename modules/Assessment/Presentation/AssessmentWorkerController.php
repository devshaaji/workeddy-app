<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Presentation;

use WorkEddy\Modules\Assessment\Application\ClaimAssessmentVideoJobUseCase;
use WorkEddy\Modules\Assessment\Application\CompleteAssessmentVideoJobUseCase;
use WorkEddy\Modules\Assessment\Application\FailAssessmentVideoJobUseCase;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Shared\Exceptions\ForbiddenException;

final class AssessmentWorkerController
{
    public function __construct(
        private readonly ClaimAssessmentVideoJobUseCase $claimJob,
        private readonly CompleteAssessmentVideoJobUseCase $completeJob,
        private readonly FailAssessmentVideoJobUseCase $failJob,
    ) {}

    public function nextJob(Request $request): Response
    {
        $this->requireWorkerToken($request);
        $workerId = $request->header('x-worker-id') ?? 'video-worker';

        return Response::json(['status' => 'ok', 'data' => $this->claimJob->execute($workerId)]);
    }

    public function complete(Request $request): Response
    {
        $this->requireWorkerToken($request);
        $body = $this->requestData($request);
        $data = $this->completeJob->execute(
            jobId: (string) ($body['job_id'] ?? ''),
            workerId: $request->header('x-worker-id') ?? 'video-worker',
            assessmentUuid: (string) ($body['assessment_uuid'] ?? ''),
            assessmentVideoUuid: (string) ($body['assessment_video_uuid'] ?? ''),
            organizationUuid: (string) ($body['organization_uuid'] ?? ''),
            metrics: is_array($body['metrics'] ?? null) ? $body['metrics'] : [],
            poseVideoStorageFileUuid: isset($body['pose_video_storage_file_uuid']) ? (string) $body['pose_video_storage_file_uuid'] : null,
            thumbnailStorageFileUuid: isset($body['thumbnail_storage_file_uuid']) ? (string) $body['thumbnail_storage_file_uuid'] : null,
            facesBlurred: (bool) ($body['faces_blurred'] ?? false),
            processingConfidence: isset($body['processing_confidence']) ? (float) $body['processing_confidence'] : null,
            poseVideoPath: isset($body['pose_video_path']) ? (string) $body['pose_video_path'] : null,
            thumbnailPath: isset($body['thumbnail_path']) ? (string) $body['thumbnail_path'] : null,
            videoSha256: isset($body['video_sha256']) ? (string) $body['video_sha256'] : null,
            processingProfileHash: isset($body['processing_profile_hash']) ? (string) $body['processing_profile_hash'] : null,
            timeline: is_array($body['timeline'] ?? null) ? $body['timeline'] : [],
            riskyWindows: is_array($body['risky_windows'] ?? null) ? $body['risky_windows'] : [],
            blurredVideoStorageFileUuid: isset($body['blurred_video_storage_file_uuid']) ? (string) $body['blurred_video_storage_file_uuid'] : null,
            blurredVideoPath: isset($body['blurred_video_path']) ? (string) $body['blurred_video_path'] : null,
        );

        return Response::json(['status' => 'ok', 'data' => $data]);
    }

    public function fail(Request $request): Response
    {
        $this->requireWorkerToken($request);
        $body = $this->requestData($request);
        $data = $this->failJob->execute(
            jobId: (string) ($body['job_id'] ?? ''),
            workerId: $request->header('x-worker-id') ?? 'video-worker',
            assessmentUuid: (string) ($body['assessment_uuid'] ?? ''),
            assessmentVideoUuid: (string) ($body['assessment_video_uuid'] ?? ''),
            organizationUuid: (string) ($body['organization_uuid'] ?? ''),
            errorMessage: (string) ($body['error_message'] ?? 'Processing failed.'),
        );

        return Response::json(['status' => 'ok', 'data' => $data]);
    }

    /** @return array<string, mixed> */
    private function requestData(Request $request): array
    {
        return array_replace($request->body, $request->json);
    }

    private function requireWorkerToken(Request $request): void
    {
        $expected = trim((string) (getenv('WORKER_API_TOKEN') ?: ''));
        $provided = trim((string) ($request->header('x-worker-token') ?? ''));
        if ($expected === '' || !hash_equals($expected, $provided)) {
            throw new ForbiddenException('Invalid worker token.');
        }
    }
}
