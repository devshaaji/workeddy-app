<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Assessment\Application\Processing\AssessmentVideoProcessingProfile;
use WorkEddy\Modules\Assessment\Application\Processing\AssessmentVideoProcessingProfileResolver;
use WorkEddy\Modules\Assessment\Application\Processing\SubscriptionAssessmentVideoProcessingProfileResolver;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Queue\IQueueService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class EnqueueAssessmentVideoProcessingUseCase
{
    public const QUEUE = 'assessment_video_jobs';
    public const JOB_TYPE = 'assessment_video.process';

    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly IQueueService $queue,
        private readonly IAuditService $audit,
        private readonly AssessmentVideoProcessingProfileResolver $profiles = new AssessmentVideoProcessingProfileResolver(),
        private readonly ?SubscriptionAssessmentVideoProcessingProfileResolver $subscriptionProfiles = null,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $assessmentUuid, string $assessmentVideoUuid, UserContext $actor, string $videoPath, ?string $planCode = null, ?array $subscriptionProfile = null, ?string $videoSha256 = null): array
    {
        $assessment = $this->findAssessment($assessmentUuid, $actor);
        if ($assessment->getModel() === 'niosh') {
            throw new ValidationException(['model' => 'NIOSH does not support video processing.']);
        }
        $profile = $subscriptionProfile !== null
            ? $this->profileFromWorkerPayload($subscriptionProfile)
            : $this->resolveProfile($assessment->getOrganizationId(), $planCode);
        $profileHash = $this->profileHash($profile);
        $video = $this->findVideo($assessment, $assessmentVideoUuid);
        if ($videoSha256 !== null) {
            $reusable = $this->assessments->findReusableVideoProcessingResult($videoSha256, $profileHash);
            if ($reusable !== null) {
                $reused = $video->markCompleted(
                    isset($reusable['poseVideoStorageFileUuid']) ? (string) $reusable['poseVideoStorageFileUuid'] : null,
                    isset($reusable['thumbnailStorageFileUuid']) ? (string) $reusable['thumbnailStorageFileUuid'] : null,
                    true,
                    isset($reusable['processingConfidence']) ? (float) $reusable['processingConfidence'] : null,
                    date('Y-m-d H:i:s'),
                    isset($reusable['blurredVideoStorageFileUuid']) ? (string) $reusable['blurredVideoStorageFileUuid'] : null,
                );
                $this->assessments->updateVideoProcessing($reused);
                $this->audit->record('assessment.video.processing_reused', 'assessment_video', $reused->getUuid(), afterState: $reused->toView(), actorId: (string) $actor->userId, actorType: 'user');

                return $reused->toView();
            }
        }

        $video = $video->markQueued();
        $this->assessments->updateVideoProcessing($video);

        $payload = [
            'assessment_uuid' => $assessment->getUuid(),
            'assessment_video_uuid' => $video->getUuid(),
            'organization_uuid' => $assessment->getOrganizationUuid(),
            'storage_file_uuid' => $video->getStorageFileUuid(),
            'model' => $assessment->getModel(),
            'video_path' => trim($videoPath),
            'face_blur_requested' => $video->isFaceBlurRequested(),
            'multi_person_policy' => 'dominant_subject',
            'processing_profile' => $profile->toWorkerPayload(),
            'processing_profile_hash' => $profileHash,
            'video_sha256' => $videoSha256,
        ];
        $this->queue->dispatch(self::JOB_TYPE, $payload, $profile->queueName);
        $this->audit->record('assessment.video.processing_queued', 'assessment_video', $video->getUuid(), afterState: $video->toView(), actorId: (string) $actor->userId, actorType: 'user');

        return $video->toView();
    }

    private function profileHash(AssessmentVideoProcessingProfile $profile): string
    {
        $payload = $profile->toWorkerPayload();
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function resolveProfile(?int $organizationId, ?string $planCode): AssessmentVideoProcessingProfile
    {
        if ($this->subscriptionProfiles !== null) {
            return $this->subscriptionProfiles->resolveForOrganization($organizationId, $planCode);
        }

        return $this->profiles->resolve($planCode);
    }

    /** @param array<string, mixed> $payload */
    private function profileFromWorkerPayload(array $payload): AssessmentVideoProcessingProfile
    {
        $maxResolution = is_array($payload['max_resolution'] ?? null) ? $payload['max_resolution'] : [];
        $queuePriority = (string) ($payload['queue_priority'] ?? 'normal');

        return new AssessmentVideoProcessingProfile(
            tier: (string) ($payload['tier'] ?? 'trial'),
            mediaPipeModel: (string) ($payload['mediapipe_model'] ?? 'lite'),
            heavyModelStrategy: isset($payload['heavy_model_strategy']) ? (string) $payload['heavy_model_strategy'] : null,
            maxDurationSeconds: (int) ($payload['max_duration_seconds'] ?? 15),
            sampledFps: (float) ($payload['sampled_fps'] ?? 1.0),
            maxResolutionWidth: (int) ($maxResolution['width'] ?? 640),
            maxResolutionHeight: (int) ($maxResolution['height'] ?? 360),
            queueName: 'assessment_video_jobs.' . $queuePriority,
            queuePriority: $queuePriority,
            reportDepth: (string) ($payload['report_depth'] ?? 'basic'),
            outputTypes: is_array($payload['output_types'] ?? null) ? array_values(array_map('strval', $payload['output_types'])) : ['thumbnail', 'basic_flags'],
            retentionRule: (string) ($payload['retention_rule'] ?? 'delete_after_processing'),
            requiresAccessAudit: (bool) ($payload['requires_access_audit'] ?? true),
            workerConcurrency: (int) ($payload['worker_concurrency'] ?? 1),
        );
    }

    private function findAssessment(string $assessmentUuid, UserContext $actor): Assessment
    {
        $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'));
        if ($assessment === null || ($actor->organizationId !== null && $actor->organizationId !== $assessment->getOrganizationId())) {
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
}
