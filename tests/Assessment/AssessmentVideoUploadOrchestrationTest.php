<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Assessment;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Assessment\Application\AttachAssessmentVideoUseCase;
use WorkEddy\Modules\Assessment\Application\CreateVideoAssessmentForProcessingUseCase;
use WorkEddy\Modules\Assessment\Application\EnqueueAssessmentVideoProcessingUseCase;
use WorkEddy\Modules\Assessment\Application\Processing\AssessmentVideoProcessingProfileResolver;
use WorkEddy\Modules\Assessment\Application\Processing\SubscriptionAssessmentVideoProcessingProfileResolver;
use WorkEddy\Modules\Assessment\Application\UploadAssessmentVideoForProcessingUseCase;
use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Settings\AssessmentSettings;
use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\AiScoreOutput;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Privacy\Application\RecordVideoConsentUseCase;
use WorkEddy\Modules\Privacy\Domain\Contracts\IPrivacyRepository;
use WorkEddy\Modules\Privacy\Domain\RetentionPolicy;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionLimitGuard;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRecorder;
use WorkEddy\Modules\Subscription\Domain\Entities\Subscription;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionPlan;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionUsage;
use WorkEddy\Modules\Subscription\Domain\Enums\SubscriptionStatus;
use WorkEddy\Modules\Subscription\Domain\ValueObjects\SubscriptionLimits;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Queue\IQueueService;
use WorkEddy\Platform\Queue\QueueJob;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\ValidationException;

final class AssessmentVideoUploadOrchestrationTest extends TestCase
{
    public function test_upload_records_consent_attaches_video_and_queues_processing(): void
    {
        $assessment = Assessment::create(
            id: 1,
            uuid: '44444444-4444-4444-8444-444444444444',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: [],
            initialScore: ['raw_score' => 0.0],
            riskFactors: [],
            bodyRegions: [],
            createdBy: 44,
        );
        $repo = new UploadAssessmentRepository($assessment);
        $storage = new UploadRecordingStorageService();
        $privacy = new UploadPrivacyRepository();
        $audit = new UploadAuditService();
        $queue = new UploadQueueService();
        $actor = new UserContext(
            userId: 44,
            organizationId: 3,
            organizationUuid: $assessment->getOrganizationUuid(),
            roleType: 'staff',
            privileges: [AssessmentPermissions::VIDEO_UPLOAD],
        );

        $useCase = new UploadAssessmentVideoForProcessingUseCase(
            $storage,
            new RecordVideoConsentUseCase($privacy, $audit, new UploadClock()),
            new AttachAssessmentVideoUseCase($repo, new UploadPermissionService(), new UploadTransactionManager(), $audit),
            new EnqueueAssessmentVideoProcessingUseCase($repo, $queue, $audit, new AssessmentVideoProcessingProfileResolver()),
            new UploadAllowingLimitGuard(),
            new UploadRecordingUsageRecorder(),
            new UploadOrganizationRepository(),
        );

        $result = $useCase->execute(
            assessmentUuid: $assessment->getUuid(),
            organizationUuid: $assessment->getOrganizationUuid(),
            actor: $actor,
            file: [
                'name' => 'lift.mp4',
                'tmp_name' => $this->fixtureFile('lift.mp4', 'video-bytes'),
                'type' => 'video/mp4',
                'size' => 1024,
                'error' => UPLOAD_ERR_OK,
            ],
            durationSeconds: 42,
            consentTextVersion: 'workeddy-video-consent-v1',
            acceptedNotice: true,
            faceBlurRequested: true,
            planCode: 'enterprise',
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        );

        self::assertSame('99999999-9999-4999-8999-999999999999', $result['storageFile']['uuid']);
        self::assertSame('queued', $result['video']['processingStatus']);
        self::assertSame('workeddy-video-consent-v1', $result['consent']['textVersion']);
        self::assertSame(['video'], $storage->storedFieldNames);
        self::assertSame(['privacy.video.consent_recorded', 'assessment.video.attached', 'assessment.video.processing_queued'], array_column($audit->records, 'action'));
        self::assertSame('assessment_video_jobs.highest', $queue->dispatched[0]['queue']);
        self::assertSame('enterprise', $queue->dispatched[0]['payload']['processing_profile']['tier']);
        self::assertSame('/storage/assessment/2026/07/stored-video.mp4', $queue->dispatched[0]['payload']['video_path']);
        self::assertSame(hash('sha256', 'video-bytes'), $queue->dispatched[0]['payload']['video_sha256']);
        self::assertNotEmpty($queue->dispatched[0]['payload']['processing_profile_hash']);
    }

    public function test_video_first_flow_creates_assessment_and_queues_processing(): void
    {
        $organization = new \WorkEddy\Modules\Organization\Domain\Organization(
            id: 3,
            uuid: '11111111-1111-4111-8111-111111111111',
            name: 'Acme Safety Group',
            slug: 'acme-safety-group',
        );
        $task = new \WorkEddy\Modules\Task\Domain\Task(
            id: 5,
            uuid: '22222222-2222-4222-8222-222222222222',
            organizationId: 3,
            worksiteId: 8,
            departmentId: 9,
            jobRoleId: 10,
            name: 'Container Lift',
            assessmentModel: 'reba',
            taskCode: null,
        );
        $repo = new \WorkEddy\Tests\Assessment\InMemoryAssessmentRepository();
        $storage = new UploadRecordingStorageService();
        $audit = new UploadAuditService();
        $queue = new UploadQueueService();
        $actor = new UserContext(
            userId: 44,
            organizationId: 3,
            organizationUuid: $organization->getUuid(),
            roleType: 'staff',
            privileges: [AssessmentPermissions::CREATE, AssessmentPermissions::VIDEO_UPLOAD],
        );

        $upload = new UploadAssessmentVideoForProcessingUseCase(
            $storage,
            new RecordVideoConsentUseCase(new UploadPrivacyRepository(), $audit, new UploadClock()),
            new AttachAssessmentVideoUseCase($repo, new UploadPermissionService(), new UploadTransactionManager(), $audit),
            new EnqueueAssessmentVideoProcessingUseCase($repo, $queue, $audit, new AssessmentVideoProcessingProfileResolver()),
            new UploadAllowingLimitGuard(),
            new UploadRecordingUsageRecorder(),
            new UploadOrganizationRepository(),
        );

        $organizationRepo = new class($organization) implements \WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository {
            public function __construct(private \WorkEddy\Modules\Organization\Domain\Organization $organization) {}
            public function create(\WorkEddy\Modules\Organization\Domain\Organization $organization): int { return 1; }
            public function update(\WorkEddy\Modules\Organization\Domain\Organization $organization): void {}
            public function findById(int $id): ?\WorkEddy\Modules\Organization\Domain\Organization { return $id === $this->organization->getId() ? $this->organization : null; }
            public function findByUuid(string $uuid): ?\WorkEddy\Modules\Organization\Domain\Organization { return $uuid === $this->organization->getUuid() ? $this->organization : null; }
            public function findBySlug(string $slug): ?\WorkEddy\Modules\Organization\Domain\Organization { return null; }
            public function findAll(int $limit = 50, int $offset = 0): array { return [$this->organization]; }
            public function softDelete(string $uuid): void {}
        };
        $taskRepo = new class($task) implements \WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository {
            public function __construct(private \WorkEddy\Modules\Task\Domain\Task $task) {}
            public function create(\WorkEddy\Modules\Task\Domain\Task $task): int { return (int) $task->getId(); }
            public function update(\WorkEddy\Modules\Task\Domain\Task $task): void {}
            public function delete(string $uuid): void {}
            public function findByUuid(string $uuid): ?\WorkEddy\Modules\Task\Domain\Task { return $uuid === $this->task->getUuid() ? $this->task : null; }
            public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array { return $organizationId === $this->task->getOrganizationId() ? [$this->task] : []; }
        };

        $useCase = new CreateVideoAssessmentForProcessingUseCase(
            $organizationRepo,
            $taskRepo,
            $repo,
            new \WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine([
                new \WorkEddy\Modules\Ergonomics\Domain\Services\RebaService(),
                new \WorkEddy\Modules\Ergonomics\Domain\Services\RulaService(),
                new \WorkEddy\Modules\Ergonomics\Domain\Services\NioshService(),
            ]),
            new UploadPermissionService(),
            new UploadTransactionManager(),
            $audit,
            new UploadAllowingLimitGuard(),
            new UploadRecordingUsageRecorder(),
            $upload,
        );

        $result = $useCase->execute(
            organizationUuid: $organization->getUuid(),
            taskUuid: $task->getUuid(),
            actor: $actor,
            file: [
                'name' => 'lift.mp4',
                'tmp_name' => $this->fixtureFile('lift-video-first.mp4', 'video-bytes'),
                'type' => 'video/mp4',
                'size' => 1024,
                'error' => UPLOAD_ERR_OK,
            ],
            durationSeconds: 42,
            consentTextVersion: 'workeddy-video-consent-v1',
            acceptedNotice: true,
            faceBlurRequested: true,
            planCode: 'enterprise',
        );

        self::assertSame('pending_review', $result['assessment']['status']);
        self::assertSame('video_pending', $result['assessment']['scoreSource']);
        self::assertSame('22222222-2222-4222-8222-222222222222', $result['assessment']['taskUuid']);
        self::assertSame('queued', $result['upload']['video']['processingStatus']);
        self::assertCount(1, $result['assessment']['videos']);
        self::assertSame('assessment_video_jobs.highest', $queue->dispatched[0]['queue']);
    }

    public function test_video_first_flow_rejects_manual_only_task_model(): void
    {
        $organization = new \WorkEddy\Modules\Organization\Domain\Organization(
            id: 3,
            uuid: '11111111-1111-4111-8111-111111111111',
            name: 'Acme Safety Group',
            slug: 'acme-safety-group',
        );
        $task = new \WorkEddy\Modules\Task\Domain\Task(
            id: 5,
            uuid: '22222222-2222-4222-8222-222222222222',
            organizationId: 3,
            worksiteId: 8,
            departmentId: 9,
            jobRoleId: 10,
            name: 'Heavy Lift',
            assessmentModel: 'niosh',
            taskCode: null,
        );
        $repo = new \WorkEddy\Tests\Assessment\InMemoryAssessmentRepository();
        $upload = new UploadAssessmentVideoForProcessingUseCase(
            new UploadRecordingStorageService(),
            new RecordVideoConsentUseCase(new UploadPrivacyRepository(), new UploadAuditService(), new UploadClock()),
            new AttachAssessmentVideoUseCase($repo, new UploadPermissionService(), new UploadTransactionManager(), new UploadAuditService()),
            new EnqueueAssessmentVideoProcessingUseCase($repo, new UploadQueueService(), new UploadAuditService(), new AssessmentVideoProcessingProfileResolver()),
            new UploadAllowingLimitGuard(),
            new UploadRecordingUsageRecorder(),
            new UploadOrganizationRepository(),
        );
        $organizationRepo = new class($organization) implements \WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository {
            public function __construct(private \WorkEddy\Modules\Organization\Domain\Organization $organization) {}
            public function create(\WorkEddy\Modules\Organization\Domain\Organization $organization): int { return 1; }
            public function update(\WorkEddy\Modules\Organization\Domain\Organization $organization): void {}
            public function findById(int $id): ?\WorkEddy\Modules\Organization\Domain\Organization { return $id === $this->organization->getId() ? $this->organization : null; }
            public function findByUuid(string $uuid): ?\WorkEddy\Modules\Organization\Domain\Organization { return $uuid === $this->organization->getUuid() ? $this->organization : null; }
            public function findBySlug(string $slug): ?\WorkEddy\Modules\Organization\Domain\Organization { return null; }
            public function findAll(int $limit = 50, int $offset = 0): array { return [$this->organization]; }
            public function softDelete(string $uuid): void {}
        };
        $taskRepo = new class($task) implements \WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository {
            public function __construct(private \WorkEddy\Modules\Task\Domain\Task $task) {}
            public function create(\WorkEddy\Modules\Task\Domain\Task $task): int { return (int) $task->getId(); }
            public function update(\WorkEddy\Modules\Task\Domain\Task $task): void {}
            public function delete(string $uuid): void {}
            public function findByUuid(string $uuid): ?\WorkEddy\Modules\Task\Domain\Task { return $uuid === $this->task->getUuid() ? $this->task : null; }
            public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array { return $organizationId === $this->task->getOrganizationId() ? [$this->task] : []; }
        };
        $useCase = new CreateVideoAssessmentForProcessingUseCase(
            $organizationRepo,
            $taskRepo,
            $repo,
            new \WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine([
                new \WorkEddy\Modules\Ergonomics\Domain\Services\RebaService(),
                new \WorkEddy\Modules\Ergonomics\Domain\Services\RulaService(),
                new \WorkEddy\Modules\Ergonomics\Domain\Services\NioshService(),
            ]),
            new UploadPermissionService(),
            new UploadTransactionManager(),
            new UploadAuditService(),
            new UploadAllowingLimitGuard(),
            new UploadRecordingUsageRecorder(),
            $upload,
        );

        $this->expectException(ValidationException::class);

        $useCase->execute(
            organizationUuid: $organization->getUuid(),
            taskUuid: $task->getUuid(),
            actor: new UserContext(
                userId: 44,
                organizationId: 3,
                organizationUuid: $organization->getUuid(),
                roleType: 'staff',
                privileges: [AssessmentPermissions::CREATE, AssessmentPermissions::VIDEO_UPLOAD],
            ),
            file: [
                'name' => 'lift.mp4',
                'tmp_name' => $this->fixtureFile('lift-video-niosh.mp4', 'video-bytes'),
                'type' => 'video/mp4',
                'size' => 1024,
                'error' => UPLOAD_ERR_OK,
            ],
            durationSeconds: 42,
            consentTextVersion: 'workeddy-video-consent-v1',
            acceptedNotice: true,
            faceBlurRequested: true,
        );
    }

    public function test_upload_uses_active_subscription_plan_features_for_processing_profile(): void
    {
        $assessment = Assessment::create(
            id: 1,
            uuid: '44444444-4444-4444-8444-444444444444',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: [],
            initialScore: ['raw_score' => 0.0],
            riskFactors: [],
            bodyRegions: [],
            createdBy: 44,
        );
        $repo = new UploadAssessmentRepository($assessment);
        $storage = new UploadRecordingStorageService();
        $audit = new UploadAuditService();
        $queue = new UploadQueueService();
        $profileResolver = new SubscriptionAssessmentVideoProcessingProfileResolver(
            new UploadSubscriptionRepository(),
            new UploadSubscriptionPlanRepository(),
            new AssessmentVideoProcessingProfileResolver(),
        );

        $useCase = new UploadAssessmentVideoForProcessingUseCase(
            $storage,
            new RecordVideoConsentUseCase(new UploadPrivacyRepository(), $audit, new UploadClock()),
            new AttachAssessmentVideoUseCase($repo, new UploadPermissionService(), new UploadTransactionManager(), $audit),
            new EnqueueAssessmentVideoProcessingUseCase($repo, $queue, $audit, new AssessmentVideoProcessingProfileResolver(), $profileResolver),
            new UploadAllowingLimitGuard(),
            new UploadRecordingUsageRecorder(),
            new UploadOrganizationRepository(),
            new AssessmentVideoProcessingProfileResolver(),
            $profileResolver,
        );

        $result = $useCase->execute(
            assessmentUuid: $assessment->getUuid(),
            organizationUuid: $assessment->getOrganizationUuid(),
            actor: new UserContext(
                userId: 44,
                organizationId: 3,
                organizationUuid: $assessment->getOrganizationUuid(),
                roleType: 'staff',
                privileges: [AssessmentPermissions::VIDEO_UPLOAD],
            ),
            file: [
                'name' => 'lift.mp4',
                'tmp_name' => $this->fixtureFile('lift-subscription.mp4', 'video-bytes'),
                'type' => 'video/mp4',
                'size' => 1024,
                'error' => UPLOAD_ERR_OK,
            ],
            durationSeconds: 90,
            consentTextVersion: 'workeddy-video-consent-v1',
            acceptedNotice: true,
            faceBlurRequested: true,
            planCode: 'trial',
        );

        $profile = $queue->dispatched[0]['payload']['processing_profile'];
        self::assertSame('assessment_video_jobs.highest', $queue->dispatched[0]['queue']);
        self::assertSame('enterprise', $result['processingProfile']['tier']);
        self::assertSame('heavy_on_risky_frames', $profile['heavy_model_strategy']);
        self::assertSame(12.0, $profile['sampled_fps']);
        self::assertSame(240, $profile['max_duration_seconds']);
        self::assertSame(['timeline', 'thumbnail', 'pose_video', 'blurred_video', 'advanced_report'], $profile['output_types']);
        self::assertFalse($profile['requires_access_audit']);
    }

    public function test_upload_rejects_video_exceeding_profile_duration(): void
    {
        $assessment = Assessment::create(
            id: 1,
            uuid: '44444444-4444-4444-8444-444444444444',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: [],
            initialScore: ['raw_score' => 0.0],
            riskFactors: [],
            bodyRegions: [],
            createdBy: 44,
        );
        $useCase = new UploadAssessmentVideoForProcessingUseCase(
            new UploadRecordingStorageService(),
            new RecordVideoConsentUseCase(new UploadPrivacyRepository(), new UploadAuditService(), new UploadClock()),
            new AttachAssessmentVideoUseCase(new UploadAssessmentRepository($assessment), new UploadPermissionService(), new UploadTransactionManager(), new UploadAuditService()),
            new EnqueueAssessmentVideoProcessingUseCase(new UploadAssessmentRepository($assessment), new UploadQueueService(), new UploadAuditService(), new AssessmentVideoProcessingProfileResolver()),
            new UploadAllowingLimitGuard(),
            new UploadRecordingUsageRecorder(),
            new UploadOrganizationRepository(),
        );

        $this->expectException(ValidationException::class);

        $useCase->execute(
            assessmentUuid: $assessment->getUuid(),
            organizationUuid: $assessment->getOrganizationUuid(),
            actor: new UserContext(userId: 44, organizationId: 3, roleType: 'staff', privileges: [AssessmentPermissions::VIDEO_UPLOAD]),
            file: [
                'name' => 'long.mp4',
                'tmp_name' => $this->fixtureFile('long.mp4', 'video-bytes'),
                'type' => 'video/mp4',
                'size' => 1024,
                'error' => UPLOAD_ERR_OK,
            ],
            durationSeconds: 60,
            consentTextVersion: 'workeddy-video-consent-v1',
            acceptedNotice: true,
            faceBlurRequested: true,
            planCode: 'trial',
        );
    }

    public function test_upload_rejects_video_exceeding_configured_size_limit(): void
    {
        $assessment = Assessment::create(
            id: 1,
            uuid: '44444444-4444-4444-8444-444444444444',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: [],
            initialScore: ['raw_score' => 0.0],
            riskFactors: [],
            bodyRegions: [],
            createdBy: 44,
        );

        $settings = new AssessmentSettings(new SettingsService([
            'assessment.max_video_size_bytes' => 512,
        ]));

        $useCase = new UploadAssessmentVideoForProcessingUseCase(
            new UploadRecordingStorageService(),
            new RecordVideoConsentUseCase(new UploadPrivacyRepository(), new UploadAuditService(), new UploadClock()),
            new AttachAssessmentVideoUseCase(new UploadAssessmentRepository($assessment), new UploadPermissionService(), new UploadTransactionManager(), new UploadAuditService()),
            new EnqueueAssessmentVideoProcessingUseCase(new UploadAssessmentRepository($assessment), new UploadQueueService(), new UploadAuditService(), new AssessmentVideoProcessingProfileResolver()),
            new UploadAllowingLimitGuard(),
            new UploadRecordingUsageRecorder(),
            new UploadOrganizationRepository(),
            new AssessmentVideoProcessingProfileResolver(),
            null,
            $settings,
        );

        $this->expectException(ValidationException::class);

        $useCase->execute(
            assessmentUuid: $assessment->getUuid(),
            organizationUuid: $assessment->getOrganizationUuid(),
            actor: new UserContext(userId: 44, organizationId: 3, roleType: 'staff', privileges: [AssessmentPermissions::VIDEO_UPLOAD]),
            file: [
                'name' => 'large.mp4',
                'tmp_name' => $this->fixtureFile('large.mp4', 'video-bytes'),
                'type' => 'video/mp4',
                'size' => 2048,
                'error' => UPLOAD_ERR_OK,
            ],
            durationSeconds: 10,
            consentTextVersion: 'workeddy-video-consent-v1',
            acceptedNotice: true,
            faceBlurRequested: true,
            planCode: 'enterprise',
        );
    }

    private function fixtureFile(string $name, string $contents): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $contents);

        return $path;
    }
}

final class UploadAssessmentRepository implements IAssessmentRepository
{
    public function __construct(public Assessment $assessment) {}
    public function create(Assessment $assessment): int { return 1; }
    public function update(Assessment $assessment): void { $this->assessment = $assessment; }
    public function addVideo(AssessmentVideo $video): int { $this->assessment = $this->assessment->withVideo($video->withId(1)); return 1; }
    public function updateVideoProcessing(AssessmentVideo $video): void { $this->assessment = $this->assessment->replaceVideo($video); }
    public function saveVideoProcessingResult(array $result): void {}
    public function findReusableVideoProcessingResult(string $videoSha256, string $processingProfileHash): ?array { return null; }
    public function saveAiScoreOutput(AiScoreOutput $output): int { return 1; }
    public function findLatestAiScoreOutput(string $assessmentUuid): ?AiScoreOutput { return null; }
    public function findByUuid(string $uuid): ?Assessment { return $uuid === $this->assessment->getUuid() ? $this->assessment : null; }
    public function findById(int $id): ?Assessment { return $id === $this->assessment->getId() ? $this->assessment : null; }
    public function findAllByOrganizationId(?int $organizationId, int $limit = 50, int $offset = 0): array { return [$this->assessment]; }
    public function createComparisonReport(\WorkEddy\Modules\Assessment\Domain\ComparisonReport $report): int { return 1; }
    public function updateComparisonReport(\WorkEddy\Modules\Assessment\Domain\ComparisonReport $report): void {}
    public function findComparisonReportByUuid(string $uuid): ?\WorkEddy\Modules\Assessment\Domain\ComparisonReport { return null; }
    public function findComparisonReportByBaselineAndFollowUp(string $baselineAssessmentUuid, string $followUpAssessmentUuid): ?\WorkEddy\Modules\Assessment\Domain\ComparisonReport { return null; }
    public function findComparisonReportsByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array { return []; }
}

final class UploadRecordingStorageService implements IStorageService
{
    public array $storedFieldNames = [];
    public function storeUploadedFile(StoreUploadedFileRequest $request): ?StoredFileDTO
    {
        $this->storedFieldNames[] = $request->fieldName;
        return new StoredFileDTO(null, '99999999-9999-4999-8999-999999999999', 'local', 'private', 'active', 'assessment/2026/07/stored-' . $request->fieldName . '.mp4', $request->ownerType, $request->ownerUuid, $request->fieldName, (string) $request->file['name'], (string) $request->file['type'], 'mp4', (int) $request->file['size']);
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

final class UploadPrivacyRepository implements IPrivacyRepository
{
    public function createConsent(array $data): array { return $data; }
    public function createVideoAccessLog(array $data): array { return $data; }
    public function listVideoConsents(?string $organizationUuid = null, int $limit = 100, int $offset = 0): array { return []; }
    public function listVideoAccessLogs(?string $organizationUuid = null, int $limit = 100, int $offset = 0): array { return []; }
    public function listVideoAssetActivity(string $organizationUuid, string $assessmentUuid, string $storageFileUuid, int $limit = 20): array { return []; }
    public function upsertRetentionPolicy(RetentionPolicy $policy): RetentionPolicy { return $policy; }
    public function findRetentionPolicyByOrganizationId(int $organizationId): ?RetentionPolicy { return null; }
    public function listRetentionPolicies(int $limit = 100, int $offset = 0): array { return []; }
}

final class UploadQueueService implements IQueueService
{
    public array $dispatched = [];
    public function dispatch(string $jobType, array $payload, ?string $queue = null, int $delaySeconds = 0): void { $this->dispatched[] = compact('jobType', 'payload', 'queue', 'delaySeconds'); }
    public function claimAvailable(string $queue, string $workerId, int $limit, int $lockSeconds): array { return []; }
    public function complete(string $jobId, string $workerId): void {}
    public function fail(string $jobId, string $workerId, string $error, int $retryDelaySeconds): void {}
    public function retryDead(string $queue, int $limit): int { return 0; }
    public function releaseStaleLocks(int $limit): int { return 0; }
    public function counts(?string $queue = null): array { return []; }
}

final class UploadAuditService implements IAuditService
{
    public array $records = [];
    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void { $this->records[] = compact('action', 'entityType', 'entityId', 'beforeState', 'afterState', 'actorId', 'actorType', 'idempotencyKey', 'metadata'); }
}

final class UploadClock implements IClock { public function now(): \DateTimeImmutable { return new \DateTimeImmutable('2026-07-08 10:00:00'); } }
final class UploadTransactionManager implements TransactionManagerInterface { public function transactional(callable $callback): mixed { return $callback(); } }
final class UploadPermissionService implements IPermissionService { public function requirePrivilege(UserContext $ctx, string $privilege): void {} }
final class UploadAllowingLimitGuard implements ISubscriptionLimitGuard
{
    public function forOrganization(int $organizationId, string $metric): SubscriptionLimits
    {
        return SubscriptionLimits::fromValues($metric, 1024, 0);
    }

    public function wouldExceed(int $organizationId, string $metric, int $increment = 1): bool
    {
        return false;
    }
}
final class UploadRecordingUsageRecorder implements ISubscriptionUsageRecorder
{
    public array $records = [];

    public function forOrganization(int $organizationId, string $metric, int $increment = 1): SubscriptionUsage
    {
        $this->records[] = compact('organizationId', 'metric', 'increment');

        return new SubscriptionUsage(
            subscriptionUuid: 'sub-upload',
            periodStart: new \DateTimeImmutable('2026-07-01'),
            periodEnd: new \DateTimeImmutable('2026-07-31'),
            usageData: [$metric => $increment],
            updatedAt: new \DateTimeImmutable('2026-07-08 10:00:00'),
        );
    }
}
final class UploadOrganizationRepository implements \WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository
{
    public function create(\WorkEddy\Modules\Organization\Domain\Organization $organization): int { return 1; }
    public function update(\WorkEddy\Modules\Organization\Domain\Organization $organization): void {}
    public function findById(int $id): ?\WorkEddy\Modules\Organization\Domain\Organization { return null; }
    public function findByUuid(string $uuid): ?\WorkEddy\Modules\Organization\Domain\Organization
    {
        return new \WorkEddy\Modules\Organization\Domain\Organization(3, $uuid, 'Acme Safety Group', 'acme-safety-group');
    }
    public function findBySlug(string $slug): ?\WorkEddy\Modules\Organization\Domain\Organization { return null; }
    public function findAll(int $limit = 50, int $offset = 0): array { return []; }
    public function softDelete(string $uuid): void {}
}

final class UploadSubscriptionRepository implements ISubscriptionRepository
{
    public function createSubscription(array $data): Subscription { throw new \RuntimeException('Not needed.'); }
    public function findSubscriptionByUuid(string $uuid): ?Subscription { return null; }
    public function findByOrganizationId(int $organizationId): ?Subscription { return $this->findActiveByOrganizationId($organizationId); }
    public function findActiveByOrganizationId(int $organizationId): ?Subscription
    {
        if ($organizationId !== 3) {
            return null;
        }

        return new Subscription(
            id: 10,
            uuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            planCode: 'enterprise_custom',
            planName: 'Enterprise Custom',
            status: SubscriptionStatus::ACTIVE,
            billingCycle: 'monthly',
            startDate: new \DateTimeImmutable('2026-07-01'),
            expiryDate: null,
            activatedAt: new \DateTimeImmutable('2026-07-01'),
            suspendedAt: null,
            suspendedReason: null,
            cancelledAt: null,
            cancellationReason: null,
            autoRenew: true,
            createdAt: new \DateTimeImmutable('2026-07-01'),
            updatedAt: new \DateTimeImmutable('2026-07-01'),
        );
    }
    public function updateSubscription(Subscription $subscription, array $data): Subscription { return $subscription; }
    public function cancelSubscription(string $uuid, \DateTimeImmutable $cancelledAt, ?string $reason): Subscription { throw new \RuntimeException('Not needed.'); }
    public function changePlan(string $uuid, string $newPlanCode, string $newPlanName, \DateTimeImmutable $effectiveDate): Subscription { throw new \RuntimeException('Not needed.'); }
    public function listSubscriptions(array $filters = []): array { return []; }
    public function findDueForRenewal(\DateTimeImmutable $asOf): array { return []; }
}

final class UploadSubscriptionPlanRepository implements ISubscriptionPlanRepository
{
    public function findByCode(string $code): ?SubscriptionPlan
    {
        if ($code !== 'enterprise_custom') {
            return null;
        }

        return new SubscriptionPlan(
            id: 9,
            code: 'enterprise_custom',
            name: 'Enterprise Custom',
            description: null,
            billingCycle: 'monthly',
            price: 0.0,
            currency: 'USD',
            features: [
                'video_processing_tier' => 'enterprise',
                'video_processing_profile' => [
                    'heavy_model_strategy' => 'heavy_on_risky_frames',
                    'max_duration_seconds' => 240,
                    'sampled_fps' => 12.0,
                    'queue_priority' => 'highest',
                    'output_types' => ['timeline', 'thumbnail', 'pose_video', 'blurred_video', 'advanced_report'],
                    'requires_access_audit' => false,
                ],
            ],
            isActive: true,
            displayOrder: 1,
            createdAt: new \DateTimeImmutable('2026-07-01'),
            updatedAt: new \DateTimeImmutable('2026-07-01'),
        );
    }
    public function listActive(): array { return []; }
    public function listAll(): array { return []; }
    public function upsert(array $data): SubscriptionPlan { throw new \RuntimeException('Not needed.'); }
}
