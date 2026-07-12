<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Privacy;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\AiScoreOutput;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Privacy\Application\EnforceVideoRetentionUseCase;
use WorkEddy\Modules\Privacy\Application\LogVideoAccessUseCase;
use WorkEddy\Modules\Privacy\Application\RecordVideoConsentUseCase;
use WorkEddy\Modules\Privacy\Application\UpdateRetentionPolicyUseCase;
use WorkEddy\Modules\Privacy\Domain\Contracts\IPrivacyRepository;
use WorkEddy\Modules\Privacy\Domain\RetentionPolicy;
use WorkEddy\Modules\Privacy\Settings\PrivacySettings;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\ModuleSettings;
use WorkEddy\Shared\Exceptions\ValidationException;

final class PrivacyModuleTest extends TestCase
{
    public function test_service_provider_exposes_settings_and_permissions(): void
    {
        $provider = new \WorkEddy\Modules\Privacy\ServiceProvider();

        self::assertSame('privacy', $provider->getName());
        self::assertNotNull($provider->getSettingsProvider());
        self::assertSame('privacy', $provider->getSettingsProvider()?->getModuleName());
        self::assertNotSame([], $provider->getSettingsProvider()?->getDefinitions());
        self::assertNotNull($provider->getPermissionDefinitionProvider());
        self::assertSame('privacy', $provider->getPermissionDefinitionProvider()?->module());
        self::assertTrue(is_subclass_of(PrivacySettings::class, ModuleSettings::class));
        self::assertFileExists((string) $provider->getRouteFile());
        self::assertFileExists(dirname((string) $provider->getRouteFile()) . '/Views/consent.php');
        self::assertFileExists(dirname((string) $provider->getRouteFile()) . '/Views/retention.php');
        self::assertFileExists(dirname((string) $provider->getRouteFile()) . '/Views/video_access_log.php');
    }

    public function test_consent_access_log_and_retention_workflow(): void
    {
        $privacy = new InMemoryPrivacyRepository();
        $audit = new RecordingPrivacyAuditService();
        $clock = new FixedPrivacyClock('2026-07-07 10:00:00');
        $actor = new UserContext(userId: 44, organizationId: 3, organizationUuid: '11111111-1111-4111-8111-111111111111', roleType: 'staff', privileges: []);

        $recordConsent = new RecordVideoConsentUseCase($privacy, $audit, $clock);
        $consent = $recordConsent->execute(
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            assessmentUuid: '44444444-4444-4444-8444-444444444444',
            storageFileUuid: '33333333-3333-4333-8333-333333333333',
            actor: $actor,
            textVersion: 'workeddy-video-consent-v1',
            acceptedNotice: true,
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        );

        self::assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $consent['uuid']);
        self::assertSame('workeddy-video-consent-v1', $consent['textVersion']);
        self::assertSame('2026-07-07 10:00:00', $consent['acceptedAt']);

        $logAccess = new LogVideoAccessUseCase($privacy, $audit, $clock);
        $access = $logAccess->execute(
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            assessmentUuid: '44444444-4444-4444-8444-444444444444',
            storageFileUuid: '33333333-3333-4333-8333-333333333333',
            actor: $actor,
            purpose: 'review',
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        );

        self::assertSame('review', $access['purpose']);
        self::assertSame('privacy.video.access_logged', $audit->records[1]['action']);

        $updatePolicy = new UpdateRetentionPolicyUseCase($privacy, $audit, $clock);
        $policy = $updatePolicy->execute(
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            actor: $actor,
            rawVideoPolicy: RetentionPolicy::RAW_DELETE_AFTER_PROCESSING,
            retainScreenshotsOnly: true,
            retainForPilotEvidence: false,
            retentionDays: 0,
        );

        self::assertSame(RetentionPolicy::RAW_DELETE_AFTER_PROCESSING, $policy['rawVideoPolicy']);
        self::assertTrue($policy['retainScreenshotsOnly']);

        $assessment = Assessment::create(
            id: 1,
            uuid: '44444444-4444-4444-8444-444444444444',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: [],
            initialScore: ['raw_score' => 8.0],
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
            processingStatus: 'completed',
            createdAt: '2026-07-07 09:00:00',
        ));
        $storage = new RecordingStorageService();
        $enforceRetention = new EnforceVideoRetentionUseCase(
            $privacy,
            new SinglePrivacyAssessmentRepository($assessment),
            $storage,
            $audit,
        );

        $result = $enforceRetention->execute($assessment->getUuid(), $actor);

        self::assertSame(['33333333-3333-4333-8333-333333333333'], $result['deletedStorageFileUuids']);
        self::assertSame(['33333333-3333-4333-8333-333333333333'], $storage->deleted);
        self::assertSame(['privacy.video.consent_recorded', 'privacy.video.access_logged', 'privacy.retention_policy.updated', 'privacy.video.retention_enforced'], array_column($audit->records, 'action'));
    }

    public function test_video_consent_requires_acceptance(): void
    {
        $useCase = new RecordVideoConsentUseCase(
            new InMemoryPrivacyRepository(),
            new RecordingPrivacyAuditService(),
            new FixedPrivacyClock('2026-07-07 10:00:00'),
        );

        $this->expectException(ValidationException::class);

        $useCase->execute(
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            assessmentUuid: '44444444-4444-4444-8444-444444444444',
            storageFileUuid: '33333333-3333-4333-8333-333333333333',
            actor: new UserContext(userId: 44, organizationId: 3, roleType: 'staff', privileges: []),
            textVersion: 'workeddy-video-consent-v1',
            acceptedNotice: false,
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        );
    }

    public function test_required_privacy_statement_appears_before_sensitive_submission(): void
    {
        $statement = 'WorkEddy is designed for ergonomic risk prevention and safety improvement, not worker discipline or productivity surveillance.';
        foreach ([
            __DIR__ . '/../../modules/Privacy/Presentation/Views/retention.php',
            __DIR__ . '/../../modules/Privacy/Presentation/Views/consent.php',
        ] as $path) {
            self::assertStringContainsString($statement, (string) file_get_contents($path), $path);
        }
    }

    public function test_video_asset_activity_filters_by_org_assessment_and_asset(): void
    {
        $privacy = new InMemoryPrivacyRepository();
        $privacy->accessLogs = [
            [
                'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'organizationUuid' => '11111111-1111-4111-8111-111111111111',
                'assessmentUuid' => '44444444-4444-4444-8444-444444444444',
                'storageFileUuid' => '33333333-3333-4333-8333-333333333333',
                'purpose' => 'review',
                'action' => 'privacy.video.signed_access_issued',
                'accessedAt' => '2026-07-07 10:00:00',
            ],
            [
                'uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                'organizationUuid' => '11111111-1111-4111-8111-111111111111',
                'assessmentUuid' => '44444444-4444-4444-8444-444444444444',
                'storageFileUuid' => '33333333-3333-4333-8333-333333333333',
                'purpose' => 'review',
                'action' => 'privacy.video.signed_access_streamed',
                'accessedAt' => '2026-07-07 10:01:00',
            ],
            [
                'uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
                'organizationUuid' => '22222222-2222-4222-8222-222222222222',
                'assessmentUuid' => '55555555-5555-4555-8555-555555555555',
                'storageFileUuid' => '44444444-4444-4444-8444-444444444444',
                'purpose' => 'review',
                'action' => 'privacy.video.signed_access_issued',
                'accessedAt' => '2026-07-07 10:02:00',
            ],
        ];

        $rows = $privacy->listVideoAssetActivity(
            '11111111-1111-4111-8111-111111111111',
            '44444444-4444-4444-8444-444444444444',
            '33333333-3333-4333-8333-333333333333',
            10,
        );

        self::assertCount(2, $rows);
        self::assertSame(
            ['privacy.video.signed_access_streamed', 'privacy.video.signed_access_issued'],
            array_column($rows, 'action'),
        );
    }
}

final class InMemoryPrivacyRepository implements IPrivacyRepository
{
    public array $consents = [];
    public array $accessLogs = [];
    public array $policies = [];

    public function createConsent(array $data): array
    {
        $this->consents[] = $data;
        return $data;
    }

    public function createVideoAccessLog(array $data): array
    {
        $this->accessLogs[] = $data;
        return $data;
    }

    public function listVideoConsents(?string $organizationUuid = null, int $limit = 100, int $offset = 0): array
    {
        $rows = array_values(array_filter($this->consents, static fn(array $row): bool => $organizationUuid === null || $row['organizationUuid'] === $organizationUuid));
        return array_slice($rows, $offset, $limit);
    }

    public function listVideoAccessLogs(?string $organizationUuid = null, int $limit = 100, int $offset = 0): array
    {
        $rows = array_values(array_filter($this->accessLogs, static fn(array $row): bool => $organizationUuid === null || $row['organizationUuid'] === $organizationUuid));
        return array_slice($rows, $offset, $limit);
    }

    public function listVideoAssetActivity(string $organizationUuid, string $assessmentUuid, string $storageFileUuid, int $limit = 20): array
    {
        $rows = array_values(array_filter($this->accessLogs, static function (array $row) use ($organizationUuid, $assessmentUuid, $storageFileUuid): bool {
            return $row['organizationUuid'] === $organizationUuid
                && $row['assessmentUuid'] === $assessmentUuid
                && $row['storageFileUuid'] === $storageFileUuid;
        }));

        usort($rows, static fn(array $left, array $right): int => strcmp((string) ($right['accessedAt'] ?? ''), (string) ($left['accessedAt'] ?? '')));

        return array_slice($rows, 0, max(1, $limit));
    }

    public function upsertRetentionPolicy(RetentionPolicy $policy): RetentionPolicy
    {
        $this->policies[$policy->organizationId] = $policy;
        return $policy;
    }

    public function findRetentionPolicyByOrganizationId(int $organizationId): ?RetentionPolicy
    {
        return $this->policies[$organizationId] ?? null;
    }

    public function listRetentionPolicies(int $limit = 100, int $offset = 0): array
    {
        return array_slice(array_values($this->policies), $offset, $limit);
    }
}

final class RecordingPrivacyAuditService implements IAuditService
{
    public array $records = [];

    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
        $this->records[] = compact('action', 'entityType', 'entityId', 'beforeState', 'afterState', 'actorId', 'actorType', 'idempotencyKey', 'metadata');
    }
}

final class FixedPrivacyClock implements IClock
{
    public function __construct(private readonly string $now) {}

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->now);
    }
}

final class SinglePrivacyAssessmentRepository implements IAssessmentRepository
{
    public function __construct(private readonly Assessment $assessment) {}
    public function create(Assessment $assessment): int { return (int) $assessment->getId(); }
    public function update(Assessment $assessment): void {}
    public function addVideo(AssessmentVideo $video): int { return 1; }
    public function updateVideoProcessing(AssessmentVideo $video): void {}
    public function saveVideoProcessingResult(array $result): void {}
    public function findReusableVideoProcessingResult(string $videoSha256, string $processingProfileHash): ?array { return null; }
    public function saveAiScoreOutput(AiScoreOutput $output): int { return 1; }
    public function findLatestAiScoreOutput(string $assessmentUuid): ?AiScoreOutput { return null; }
    public function findByUuid(string $uuid): ?Assessment { return $uuid === $this->assessment->getUuid() ? $this->assessment : null; }
    public function findById(int $id): ?Assessment { return $id === $this->assessment->getId() ? $this->assessment : null; }
    public function findAllByOrganizationId(?int $organizationId, int $limit = 50, int $offset = 0): array { return $organizationId === null || $organizationId === $this->assessment->getOrganizationId() ? [$this->assessment] : []; }
    public function createComparisonReport(\WorkEddy\Modules\Assessment\Domain\ComparisonReport $report): int { return 1; }
    public function updateComparisonReport(\WorkEddy\Modules\Assessment\Domain\ComparisonReport $report): void {}
    public function findComparisonReportByUuid(string $uuid): ?\WorkEddy\Modules\Assessment\Domain\ComparisonReport { return null; }
    public function findComparisonReportByBaselineAndFollowUp(string $baselineAssessmentUuid, string $followUpAssessmentUuid): ?\WorkEddy\Modules\Assessment\Domain\ComparisonReport { return null; }
    public function findComparisonReportsByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array { return []; }
}

final class RecordingStorageService implements IStorageService
{
    public array $deleted = [];

    public function storeUploadedFile(StoreUploadedFileRequest $request): ?StoredFileDTO { return null; }
    public function findByUuid(string $uuid, bool $includePendingDeletion = false): StoredFileDTO { throw new \RuntimeException('Not needed.'); }
    public function list(array $filters = []): array { return []; }
    public function count(array $filters = []): int { return 0; }
    public function summary(array $filters = []): array { return ['totalFiles' => 0, 'totalBytes' => 0, 'byCategory' => []]; }
    public function read(string $uuid): string { return ''; }
    public function requestDeletion(string $uuid, ?int $actorId = null): StoredFileDTO { throw new \RuntimeException('Not needed.'); }
    public function restore(string $uuid, ?int $actorId = null): StoredFileDTO { throw new \RuntimeException('Not needed.'); }
    public function delete(string $uuid, ?int $actorId = null): StoredFileDTO
    {
        $this->deleted[] = $uuid;
        return new StoredFileDTO(null, $uuid, 'local', 'private', 'deleted', 'videos/' . $uuid . '.mp4', 'assessment', '44444444-4444-4444-8444-444444444444', 'video', 'lift.mp4', 'video/mp4', 'mp4', 1048576);
    }
    public function usageCount(string $uuid): int { return 0; }
}
