<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Assessment;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Assessment\Application\ClaimAssessmentVideoJobUseCase;
use WorkEddy\Modules\Assessment\Application\CompleteAssessmentVideoJobUseCase;
use WorkEddy\Modules\Assessment\Application\EnqueueAssessmentVideoProcessingUseCase;
use WorkEddy\Modules\Assessment\Application\FailAssessmentVideoJobUseCase;
use WorkEddy\Modules\Assessment\Application\Processing\AssessmentVideoProcessingProfileResolver;
use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\AiScoreOutput;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\Ergonomics\Domain\Services\RebaService;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Queue\IQueueService;
use WorkEddy\Platform\Queue\QueueJob;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ValidationException;

final class AssessmentVideoProcessingTest extends TestCase
{
    public function test_video_processing_queue_claim_complete_and_fail_flow(): void
    {
        $assessment = $this->assessment(model: 'reba');
        $repo = new ProcessingAssessmentRepository($assessment);
        $queue = new RecordingAssessmentQueueService();
        $audit = new RecordingProcessingAuditService();
        $actor = new UserContext(userId: 44, organizationId: 3, organizationUuid: $assessment->getOrganizationUuid(), roleType: 'staff', privileges: []);

        $queued = (new EnqueueAssessmentVideoProcessingUseCase($repo, $queue, $audit, new AssessmentVideoProcessingProfileResolver()))->execute(
            assessmentUuid: $assessment->getUuid(),
            assessmentVideoUuid: '66666666-6666-4666-8666-666666666666',
            actor: $actor,
            videoPath: '/storage/uploads/videos/lift.mp4',
            planCode: 'pro',
        );

        self::assertSame('queued', $queued['processingStatus']);
        self::assertSame('assessment_video_jobs.high', $queue->dispatched[0]['queue']);
        self::assertSame('assessment_video.process', $queue->dispatched[0]['jobType']);
        self::assertSame('pro', $queue->dispatched[0]['payload']['processing_profile']['tier']);
        self::assertSame(5.0, $queue->dispatched[0]['payload']['processing_profile']['sampled_fps']);
        self::assertSame('full', $queue->dispatched[0]['payload']['processing_profile']['mediapipe_model']);
        self::assertSame(['timeline', 'thumbnail', 'pose_video', 'standard_report'], $queue->dispatched[0]['payload']['processing_profile']['output_types']);

        $queue->claimed[] = new QueueJob(
            jobId: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            queue: 'assessment_video_jobs',
            jobType: 'assessment_video.process',
            payload: $queue->dispatched[0]['payload'],
            status: 'processing',
            attempts: 1,
            maxAttempts: 3,
            lockedBy: 'video-worker-1',
        );

        $job = (new ClaimAssessmentVideoJobUseCase($repo, $queue))->execute('video-worker-1');
        self::assertSame($assessment->getUuid(), $job['assessment_uuid']);
        self::assertSame('66666666-6666-4666-8666-666666666666', $job['assessment_video_uuid']);
        self::assertSame('33333333-3333-4333-8333-333333333333', $job['storage_file_uuid']);

        $storage = new RecordingOutputStorageService();
        $completed = (new CompleteAssessmentVideoJobUseCase($repo, $queue, new AssessmentEngine([new RebaService()]), $audit, $storage))->execute(
            jobId: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            workerId: 'video-worker-1',
            assessmentUuid: $assessment->getUuid(),
            assessmentVideoUuid: '66666666-6666-4666-8666-666666666666',
            organizationUuid: $assessment->getOrganizationUuid(),
            metrics: [
                'trunk_angle' => 60,
                'neck_angle' => 25,
                'upper_arm_angle' => 90,
                'lower_arm_angle' => 45,
                'wrist_angle' => 20,
                'leg_score' => 2,
                'processing_confidence' => 0.91,
            ],
            poseVideoPath: $this->fixtureFile('pose-output.mp4', 'pose-video'),
            thumbnailPath: $this->fixtureFile('thumb.jpg', 'thumbnail'),
            facesBlurred: true,
            processingConfidence: 0.91,
            videoSha256: '79fd615a866fe7f9eb4da8d9c41ab57e3bd48056df42fd2c13e4d461a87afbe3',
            processingProfileHash: 'profile-hash',
            timeline: [['time_seconds' => 0.0, 'trunk_angle' => 60, 'risk' => 'high']],
            riskyWindows: [['start_seconds' => 0.0, 'end_seconds' => 2.0, 'risk' => 'high']],
        );

        self::assertSame('completed', $completed['processingStatus']);
        self::assertSame('stored-pose_video', $completed['poseVideoStorageFileUuid']);
        self::assertSame('stored-thumbnail', $completed['thumbnailStorageFileUuid']);
        self::assertSame(['pose_video', 'thumbnail'], $storage->storedFieldNames);
        self::assertSame('profile-hash', $repo->processingResults[0]['processingProfileHash']);
        self::assertSame([['time_seconds' => 0.0, 'trunk_angle' => 60, 'risk' => 'high']], $repo->processingResults[0]['timeline']);
        self::assertSame([['start_seconds' => 0.0, 'end_seconds' => 2.0, 'risk' => 'high']], $repo->processingResults[0]['riskyWindows']);
        self::assertSame('stored-pose_video', $repo->processingResults[0]['poseVideoStorageFileUuid']);
        self::assertSame('stored-thumbnail', $repo->processingResults[0]['thumbnailStorageFileUuid']);
        self::assertSame('manual', $repo->assessment->getScoreSource());
        self::assertNull($repo->assessment->getFinalScoreData());
        self::assertCount(1, $repo->aiOutputs);
        self::assertSame('ai_estimated', $repo->aiOutputs[0]->scoreSource);
        self::assertSame(['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'], $queue->completed);

        $failed = (new FailAssessmentVideoJobUseCase($repo, $queue, $audit))->execute(
            jobId: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            workerId: 'video-worker-1',
            assessmentUuid: $assessment->getUuid(),
            assessmentVideoUuid: '66666666-6666-4666-8666-666666666666',
            organizationUuid: $assessment->getOrganizationUuid(),
            errorMessage: 'No pose landmarks detected.',
        );

        self::assertSame('failed', $failed['processingStatus']);
        self::assertSame('No pose landmarks detected.', $failed['processingError']);
        self::assertSame(['assessment.video.processing_queued', 'assessment.video.processing_completed', 'assessment.video.processing_failed'], array_column($audit->records, 'action'));
    }

    public function test_niosh_video_processing_is_rejected(): void
    {
        $repo = new ProcessingAssessmentRepository($this->assessment(model: 'niosh'));
        $useCase = new EnqueueAssessmentVideoProcessingUseCase($repo, new RecordingAssessmentQueueService(), new RecordingProcessingAuditService(), new AssessmentVideoProcessingProfileResolver());

        $this->expectException(ValidationException::class);

        $useCase->execute(
            assessmentUuid: $repo->assessment->getUuid(),
            assessmentVideoUuid: '66666666-6666-4666-8666-666666666666',
            actor: new UserContext(userId: 44, organizationId: 3, roleType: 'staff', privileges: []),
            videoPath: '/storage/uploads/videos/lift.mp4',
        );
    }

    public function test_enqueue_reuses_cached_video_processing_result_without_dispatching_worker(): void
    {
        $repo = new ProcessingAssessmentRepository($this->assessment(model: 'reba'));
        $queue = new RecordingAssessmentQueueService();
        $audit = new RecordingProcessingAuditService();
        $repo->reusableResult = [
            'poseVideoStorageFileUuid' => 'stored-pose_video',
            'thumbnailStorageFileUuid' => 'stored-thumbnail',
            'processingConfidence' => 0.87,
        ];

        $result = (new EnqueueAssessmentVideoProcessingUseCase($repo, $queue, $audit, new AssessmentVideoProcessingProfileResolver()))->execute(
            assessmentUuid: $repo->assessment->getUuid(),
            assessmentVideoUuid: '66666666-6666-4666-8666-666666666666',
            actor: new UserContext(userId: 44, organizationId: 3, roleType: 'staff', privileges: []),
            videoPath: '/storage/uploads/videos/lift.mp4',
            planCode: 'pro',
            videoSha256: '79fd615a866fe7f9eb4da8d9c41ab57e3bd48056df42fd2c13e4d461a87afbe3',
        );

        self::assertSame('completed', $result['processingStatus']);
        self::assertSame('stored-pose_video', $result['poseVideoStorageFileUuid']);
        self::assertSame('stored-thumbnail', $result['thumbnailStorageFileUuid']);
        self::assertSame([], $queue->dispatched);
        self::assertSame(['assessment.video.processing_reused'], array_column($audit->records, 'action'));
    }

    public function test_processing_profiles_are_not_simple_model_mapping(): void
    {
        $resolver = new AssessmentVideoProcessingProfileResolver();

        $trial = $resolver->resolve('trial');
        $enterprise = $resolver->resolve('enterprise');

        self::assertSame('lite', $trial->mediaPipeModel);
        self::assertSame(1.0, $trial->sampledFps);
        self::assertSame(15, $trial->maxDurationSeconds);
        self::assertSame('assessment_video_jobs.low', $trial->queueName);
        self::assertSame('heavy_on_risky_frames', $enterprise->heavyModelStrategy);
        self::assertSame(10.0, $enterprise->sampledFps);
        self::assertContains('advanced_report', $enterprise->outputTypes);
        self::assertGreaterThan($trial->maxResolutionWidth, $enterprise->maxResolutionWidth);
    }

    public function test_processing_profile_can_be_overridden_by_subscription_features(): void
    {
        $profile = (new AssessmentVideoProcessingProfileResolver())->resolve('pro', [
            'video_processing_profile' => [
                'sampled_fps' => 4,
                'max_duration_seconds' => 240,
                'queue_priority' => 'normal',
                'output_types' => ['thumbnail', 'standard_report'],
                'mediapipe_model' => 'lite',
            ],
        ]);

        self::assertSame('pro', $profile->tier);
        self::assertSame(4.0, $profile->sampledFps);
        self::assertSame(240, $profile->maxDurationSeconds);
        self::assertSame('assessment_video_jobs.normal', $profile->queueName);
        self::assertSame(['thumbnail', 'standard_report'], $profile->outputTypes);
        self::assertSame('lite', $profile->mediaPipeModel);
    }

    private function fixtureFile(string $name, string $contents): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $contents);

        return $path;
    }

    private function assessment(string $model): Assessment
    {
        return Assessment::create(
            id: 1,
            uuid: '44444444-4444-4444-8444-444444444444',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: $model,
            metrics: [],
            initialScore: ['raw_score' => 0.0],
            riskFactors: [],
            bodyRegions: [],
            createdBy: 44,
        )->withVideo(new AssessmentVideo(
            id: 1,
            uuid: '66666666-6666-4666-8666-666666666666',
            assessmentId: 1,
            storageFileUuid: '33333333-3333-4333-8333-333333333333',
            originalFilename: 'lift.mp4',
            mimeType: 'video/mp4',
            sizeBytes: 1048576,
            durationSeconds: 42,
            consentTextVersion: 'workeddy-video-consent-v1',
            faceBlurRequested: true,
        ));
    }
}

final class ProcessingAssessmentRepository implements IAssessmentRepository
{
    public array $processingResults = [];
    public array $aiOutputs = [];
    public ?array $reusableResult = null;

    public function __construct(public Assessment $assessment) {}
    public function create(Assessment $assessment): int { return 1; }
    public function update(Assessment $assessment): void { $this->assessment = $assessment; }
    public function addVideo(AssessmentVideo $video): int { return 1; }
    public function updateVideoProcessing(AssessmentVideo $video): void { $this->assessment = $this->assessment->replaceVideo($video); }
    public function saveVideoProcessingResult(array $result): void { $this->processingResults[] = $result; }
    public function findReusableVideoProcessingResult(string $videoSha256, string $processingProfileHash): ?array { return $this->reusableResult; }
    public function saveAiScoreOutput(AiScoreOutput $output): int { $this->aiOutputs[] = $output; return count($this->aiOutputs); }
    public function findLatestAiScoreOutput(string $assessmentUuid): ?AiScoreOutput { return $this->aiOutputs === [] ? null : $this->aiOutputs[array_key_last($this->aiOutputs)]; }
    public function findByUuid(string $uuid): ?Assessment { return $uuid === $this->assessment->getUuid() ? $this->assessment : null; }
    public function findById(int $id): ?Assessment { return $id === $this->assessment->getId() ? $this->assessment : null; }
    public function findAllByOrganizationId(?int $organizationId, int $limit = 50, int $offset = 0): array { return [$this->assessment]; }
    public function createComparisonReport(\WorkEddy\Modules\Assessment\Domain\ComparisonReport $report): int { return 1; }
    public function updateComparisonReport(\WorkEddy\Modules\Assessment\Domain\ComparisonReport $report): void {}
    public function findComparisonReportByUuid(string $uuid): ?\WorkEddy\Modules\Assessment\Domain\ComparisonReport { return null; }
    public function findComparisonReportByBaselineAndFollowUp(string $baselineAssessmentUuid, string $followUpAssessmentUuid): ?\WorkEddy\Modules\Assessment\Domain\ComparisonReport { return null; }
    public function findComparisonReportsByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array { return []; }
}

final class RecordingAssessmentQueueService implements IQueueService
{
    public array $dispatched = [];
    public array $claimed = [];
    public array $completed = [];
    public array $failed = [];

    public function dispatch(string $jobType, array $payload, ?string $queue = null, int $delaySeconds = 0): void
    {
        $this->dispatched[] = compact('jobType', 'payload', 'queue', 'delaySeconds');
    }

    public function claimAvailable(string $queue, string $workerId, int $limit, int $lockSeconds): array
    {
        return $this->claimed;
    }

    public function complete(string $jobId, string $workerId): void { $this->completed[] = $jobId; }
    public function fail(string $jobId, string $workerId, string $error, int $retryDelaySeconds): void { $this->failed[] = compact('jobId', 'workerId', 'error', 'retryDelaySeconds'); }
    public function retryDead(string $queue, int $limit): int { return 0; }
    public function releaseStaleLocks(int $limit): int { return 0; }
    public function counts(?string $queue = null): array { return []; }
}

final class RecordingProcessingAuditService implements IAuditService
{
    public array $records = [];
    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
        $this->records[] = compact('action', 'entityType', 'entityId', 'beforeState', 'afterState', 'actorId', 'actorType', 'idempotencyKey', 'metadata');
    }
}

final class RecordingOutputStorageService implements IStorageService
{
    public array $storedFieldNames = [];

    public function storeUploadedFile(StoreUploadedFileRequest $request): ?StoredFileDTO
    {
        $this->storedFieldNames[] = $request->fieldName;

        return new StoredFileDTO(
            id: null,
            uuid: 'stored-' . $request->fieldName,
            disk: 'local',
            visibility: 'private',
            status: 'active',
            path: 'assessment-output/' . $request->fieldName,
            ownerType: $request->ownerType,
            ownerUuid: $request->ownerUuid,
            fieldName: $request->fieldName,
            originalName: (string) ($request->file['name'] ?? $request->fieldName),
            mimeType: (string) ($request->file['type'] ?? 'application/octet-stream'),
            extension: null,
            sizeBytes: (int) ($request->file['size'] ?? 0),
        );
    }

    public function findByUuid(string $uuid, bool $includePendingDeletion = false): StoredFileDTO { throw new \RuntimeException('Not needed.'); }
    public function list(array $filters = []): array { return []; }
    public function count(array $filters = []): int { return 0; }
    public function summary(array $filters = []): array { return ['totalFiles' => 0, 'totalBytes' => 0, 'byCategory' => []]; }
    public function read(string $uuid): string { return ''; }
    public function requestDeletion(string $uuid, ?int $actorId = null): StoredFileDTO { throw new \RuntimeException('Not needed.'); }
    public function restore(string $uuid, ?int $actorId = null): StoredFileDTO { throw new \RuntimeException('Not needed.'); }
    public function delete(string $uuid, ?int $actorId = null): StoredFileDTO { throw new \RuntimeException('Not needed.'); }
    public function usageCount(string $uuid): int { return 0; }
}
