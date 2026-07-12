<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Domain\AiScoreOutput;
use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Queue\IQueueService;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class CompleteAssessmentVideoJobUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly IQueueService $queue,
        private readonly AssessmentEngine $engine,
        private readonly IAuditService $audit,
        private readonly ?IStorageService $storage = null,
    ) {}

    /**
     * @param array<string, mixed> $metrics
     * @param list<array<string, mixed>> $timeline
     * @param list<array<string, mixed>> $riskyWindows
     * @return array<string, mixed>
     */
    public function execute(string $jobId, string $workerId, string $assessmentUuid, string $assessmentVideoUuid, string $organizationUuid, array $metrics, ?string $poseVideoStorageFileUuid = null, ?string $thumbnailStorageFileUuid = null, bool $facesBlurred = false, ?float $processingConfidence = null, ?string $poseVideoPath = null, ?string $thumbnailPath = null, ?string $videoSha256 = null, ?string $processingProfileHash = null, array $timeline = [], array $riskyWindows = [], ?string $blurredVideoStorageFileUuid = null, ?string $blurredVideoPath = null): array
    {
        $assessment = $this->findAssessment($assessmentUuid, $organizationUuid);
        $poseVideoStorageFileUuid ??= $this->registerOutput($assessment->getUuid(), 'pose_video', $poseVideoPath, ['mp4'], ['video/mp4']);
        $thumbnailStorageFileUuid ??= $this->registerOutput($assessment->getUuid(), 'thumbnail', $thumbnailPath, ['jpg', 'jpeg', 'png'], ['image/jpeg', 'image/png']);
        $blurredVideoStorageFileUuid ??= $this->registerOutput($assessment->getUuid(), 'blurred_video', $blurredVideoPath, ['mp4'], ['video/mp4']);
        $video = $this->findVideo($assessment, $assessmentVideoUuid)->markCompleted($poseVideoStorageFileUuid, $thumbnailStorageFileUuid, $facesBlurred, $processingConfidence, date('Y-m-d H:i:s'), $blurredVideoStorageFileUuid);
        $score = $this->engine->assess($assessment->getModel(), $metrics);
        $updated = $assessment->withAiEstimatedScore($metrics, $score)->replaceVideo($video);

        $this->assessments->update($updated);
        $this->assessments->updateVideoProcessing($video);
        $this->assessments->saveAiScoreOutput(new AiScoreOutput(
            id: null,
            uuid: UuidSupport::generate(),
            assessmentUuid: $assessment->getUuid(),
            assessmentVideoUuid: $video->getUuid(),
            scoreModel: $assessment->getModel(),
            scoreSource: 'ai_estimated',
            modelVersion: $this->resolveModelVersion($metrics, $score),
            confidence: $processingConfidence,
            metrics: $metrics,
            score: $score,
            timeline: $timeline,
            flags: [
                'low_confidence' => $processingConfidence !== null && $processingConfidence < 0.70,
                'faces_blurred' => $facesBlurred,
                'has_risky_windows' => $riskyWindows !== [],
            ],
            metadata: [
                'processing_profile_hash' => $processingProfileHash,
                'video_sha256' => $videoSha256,
                'pose_video_storage_file_uuid' => $poseVideoStorageFileUuid,
                'thumbnail_storage_file_uuid' => $thumbnailStorageFileUuid,
                'blurred_video_storage_file_uuid' => $blurredVideoStorageFileUuid,
                'risky_windows' => $riskyWindows,
            ],
            createdByWorker: $workerId,
            createdAt: null,
        ));
        if ($videoSha256 !== null && $processingProfileHash !== null) {
            $this->assessments->saveVideoProcessingResult([
                'assessmentUuid' => $assessment->getUuid(),
                'assessmentVideoUuid' => $video->getUuid(),
                'videoSha256' => $videoSha256,
                'processingProfileHash' => $processingProfileHash,
                'metrics' => $metrics,
                'timeline' => $timeline,
                'riskyWindows' => $riskyWindows,
                'poseVideoStorageFileUuid' => $poseVideoStorageFileUuid,
                'thumbnailStorageFileUuid' => $thumbnailStorageFileUuid,
                'blurredVideoStorageFileUuid' => $blurredVideoStorageFileUuid,
            ]);
        }
        $this->queue->complete($jobId, $workerId);
        $this->audit->record('assessment.video.processing_completed', 'assessment_video', $video->getUuid(), afterState: $video->toView(), actorType: 'worker');

        return $video->toView();
    }

    /**
     * @param list<string> $allowedExtensions
     * @param list<string> $allowedMimeTypes
     */
    private function registerOutput(string $assessmentUuid, string $fieldName, ?string $path, array $allowedExtensions, array $allowedMimeTypes): ?string
    {
        if ($path === null || trim($path) === '' || $this->storage === null) {
            return null;
        }
        if (!is_file($path)) {
            throw new NotFoundException('Worker output file not found.');
        }

        $stored = $this->storage->storeUploadedFile(new StoreUploadedFileRequest(
            file: [
                'name' => basename($path),
                'tmp_name' => $path,
                'size' => filesize($path) ?: 0,
                'type' => mime_content_type($path) ?: null,
                'error' => UPLOAD_ERR_OK,
            ],
            ownerType: 'assessment',
            ownerUuid: $assessmentUuid,
            fieldName: $fieldName,
            visibility: 'private',
            actorId: null,
            allowedExtensions: $allowedExtensions,
            allowedMimeTypes: $allowedMimeTypes,
        ));

        return $stored?->uuid;
    }

    private function findAssessment(string $assessmentUuid, string $organizationUuid): Assessment
    {
        $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'));
        if ($assessment === null || $assessment->getOrganizationUuid() !== UuidSupport::requireValid($organizationUuid, 'organizationUuid')) {
            throw new NotFoundException('Assessment not found.');
        }

        return $assessment;
    }

    private function findVideo(Assessment $assessment, string $videoUuid): AssessmentVideo
    {
        UuidSupport::requireValid($videoUuid, 'assessmentVideoUuid');
        foreach ($assessment->getVideos() as $video) {
            if ($video->getUuid() === $videoUuid) {
                return $video;
            }
        }

        throw new NotFoundException('Assessment video not found.');
    }

    /**
     * @param array<string, mixed> $metrics
     * @param array<string, mixed> $score
     */
    private function resolveModelVersion(array $metrics, array $score): string
    {
        $version = $metrics['model_version'] ?? $metrics['pose_model_version'] ?? $score['algorithm_version'] ?? null;

        return is_string($version) && trim($version) !== '' ? trim($version) : 'unknown';
    }
}
