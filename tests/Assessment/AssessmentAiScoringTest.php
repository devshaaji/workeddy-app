<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Assessment;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Assessment\Application\GetAssessmentUseCase;
use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\AiScoreOutput;
use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;
use WorkEddy\Modules\Assessment\Domain\ComparisonReport;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Assessment\Infrastructure\AssessmentRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Reporting\Application\Services\ReportingSnapshotService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Schema\Modules\Assessment\AssessmentSchemaBuilder;
use WorkEddy\Platform\Session\UserContext;

final class AssessmentAiScoringTest extends TestCase
{
    public function test_ai_score_output_schema_builder_adds_separate_table(): void
    {
        $schema = new Schema();
        (new AssessmentSchemaBuilder())->build($schema);

        self::assertTrue($schema->hasTable('ai_score_outputs'));
    }

    public function test_assessment_schema_builder_includes_blurred_video_columns(): void
    {
        $schema = new Schema();
        (new AssessmentSchemaBuilder())->build($schema);

        self::assertTrue($schema->getTable('assessment_videos')->hasColumn('blurred_storage_file_uuid'));
        self::assertTrue($schema->getTable('assessment_video_processing_results')->hasColumn('blurred_storage_file_uuid'));
    }

    public function test_repository_persists_ai_score_output_separately_from_final_score(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = new Schema();
        (new AssessmentSchemaBuilder())->build($schema);

        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $repository = new AssessmentRepository($connection, new FixedAiClock());
        $repository->saveAiScoreOutput(new AiScoreOutput(
            id: null,
            uuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            assessmentUuid: '44444444-4444-4444-8444-444444444444',
            assessmentVideoUuid: '66666666-6666-4666-8666-666666666666',
            scoreModel: 'reba',
            scoreSource: 'ai_estimated',
            modelVersion: 'mediapipe-pose-landmarker-lite@2026-07',
            confidence: 0.61,
            metrics: ['trunk_angle' => 44.0],
            score: ['raw_score' => 7, 'risk_level' => 'high'],
            timeline: [['time_seconds' => 0.0, 'trunk_angle' => 44.0]],
            flags: ['low_confidence' => true],
            metadata: ['profile_hash' => 'profile-hash'],
            createdByWorker: 'video-worker-1',
            createdAt: null,
        ));

        $saved = $repository->findLatestAiScoreOutput('44444444-4444-4444-8444-444444444444');

        self::assertNotNull($saved);
        self::assertSame('ai_estimated', $saved?->scoreSource);
        self::assertSame('mediapipe-pose-landmarker-lite@2026-07', $saved?->modelVersion);
    }

    public function test_in_memory_ai_score_output_does_not_change_assessment_final_score(): void
    {
        $repo = new InMemoryAiAssessmentRepository($this->assessment());

        $repo->saveAiScoreOutput(new AiScoreOutput(
            id: null,
            uuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            assessmentUuid: $repo->assessment->getUuid(),
            assessmentVideoUuid: '66666666-6666-4666-8666-666666666666',
            scoreModel: 'reba',
            scoreSource: 'ai_estimated',
            modelVersion: 'mediapipe-pose-landmarker-lite@2026-07',
            confidence: 0.61,
            metrics: ['trunk_angle' => 44.0],
            score: ['raw_score' => 7, 'risk_level' => 'high'],
            timeline: [],
            flags: ['low_confidence' => true],
            metadata: [],
            createdByWorker: 'video-worker-1',
            createdAt: null,
        ));

        $latest = $repo->findLatestAiScoreOutput($repo->assessment->getUuid());

        self::assertSame('ai_estimated', $latest?->scoreSource);
        self::assertSame('manual', $repo->assessment->getScoreSource());
        self::assertNull($repo->assessment->getFinalScoreData());
    }

    public function test_get_assessment_exposes_advisory_ai_assistance_context(): void
    {
        $repo = new InMemoryAiAssessmentRepository($this->assessment());
        $repo->saveAiScoreOutput(new AiScoreOutput(
            id: null,
            uuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            assessmentUuid: $repo->assessment->getUuid(),
            assessmentVideoUuid: '66666666-6666-4666-8666-666666666666',
            scoreModel: 'reba',
            scoreSource: 'ai_estimated',
            modelVersion: 'mediapipe-pose-landmarker-lite@2026-07',
            confidence: 0.61,
            metrics: ['trunk_angle' => 44.0],
            score: ['raw_score' => 7, 'risk_level' => 'high'],
            timeline: [['time_seconds' => 0.0, 'trunk_angle' => 44.0]],
            flags: ['low_confidence' => true],
            metadata: ['processing_profile_hash' => 'profile-hash'],
            createdByWorker: 'video-worker-1',
            createdAt: null,
        ));

        $view = (new GetAssessmentUseCase($repo, new AllowAllAiAssessmentPermissions()))->execute(
            $repo->assessment->getUuid(),
            new UserContext(userId: 44, organizationId: 3, organizationUuid: $repo->assessment->getOrganizationUuid(), roleType: 'reviewer', privileges: [AssessmentPermissions::VIEW]),
        );

        self::assertTrue($view['aiAssistance']['available']);
        self::assertTrue($view['aiAssistance']['advisoryOnly']);
        self::assertSame('low', $view['aiAssistance']['confidenceBand']);
        self::assertSame(7.0, $view['aiAssistance']['score']['raw']);
        self::assertSame('mediapipe-pose-landmarker-lite@2026-07', $view['aiAssistance']['modelVersion']);
    }

    public function test_reporting_snapshot_does_not_publish_ai_score_as_final_report_score(): void
    {
        $repo = new InMemoryAiAssessmentRepository($this->assessment()->withAiEstimatedScore(
            ['trunk_angle' => 44.0],
            ['raw_score' => 7, 'risk_level' => 'High'],
        ));
        $repo->saveAiScoreOutput(new AiScoreOutput(
            id: null,
            uuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            assessmentUuid: $repo->assessment->getUuid(),
            assessmentVideoUuid: '66666666-6666-4666-8666-666666666666',
            scoreModel: 'reba',
            scoreSource: 'ai_estimated',
            modelVersion: 'mediapipe-pose-landmarker-lite@2026-07',
            confidence: 0.61,
            metrics: ['trunk_angle' => 44.0],
            score: ['raw_score' => 7, 'risk_level' => 'High'],
            timeline: [],
            flags: ['low_confidence' => true],
            metadata: [],
            createdByWorker: 'video-worker-1',
            createdAt: null,
        ));

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $report = (new ReportingSnapshotService($connection, assessments: $repo))->assessmentReport($repo->assessment->getUuid());

        self::assertSame('pending_reviewer_confirmation', $report['score_source']);
        self::assertSame('awaiting_reviewer_confirmation', $report['report_score_status']);
        self::assertSame(0.0, $report['risk_score']);
        self::assertSame('Pending reviewer confirmation', $report['risk_level']);
        self::assertTrue($report['ai_advisory']['available']);
        self::assertSame(7.0, $report['ai_advisory']['score']['raw']);
    }

    private function assessment(): Assessment
    {
        return Assessment::create(
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

final class FixedAiClock implements IClock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-07-09 12:00:00');
    }
}

final class InMemoryAiAssessmentRepository implements IAssessmentRepository
{
    /** @var list<AiScoreOutput> */
    public array $aiOutputs = [];

    public function __construct(public Assessment $assessment) {}

    public function create(Assessment $assessment): int { $this->assessment = $assessment; return 1; }
    public function update(Assessment $assessment): void { $this->assessment = $assessment; }
    public function addVideo(AssessmentVideo $video): int { return 1; }
    public function updateVideoProcessing(AssessmentVideo $video): void { $this->assessment = $this->assessment->replaceVideo($video); }
    public function saveVideoProcessingResult(array $result): void {}
    public function findReusableVideoProcessingResult(string $videoSha256, string $processingProfileHash): ?array { return null; }
    public function saveAiScoreOutput(AiScoreOutput $output): int { $this->aiOutputs[] = $output; return count($this->aiOutputs); }
    public function findLatestAiScoreOutput(string $assessmentUuid): ?AiScoreOutput
    {
        $matches = array_values(array_filter($this->aiOutputs, static fn(AiScoreOutput $output): bool => $output->assessmentUuid === $assessmentUuid));

        return $matches === [] ? null : $matches[array_key_last($matches)];
    }
    public function findByUuid(string $uuid): ?Assessment { return $uuid === $this->assessment->getUuid() ? $this->assessment : null; }
    public function findById(int $id): ?Assessment { return $id === $this->assessment->getId() ? $this->assessment : null; }
    public function findAllByOrganizationId(?int $organizationId, int $limit = 50, int $offset = 0): array { return [$this->assessment]; }
    public function createComparisonReport(ComparisonReport $report): int { return 1; }
    public function updateComparisonReport(ComparisonReport $report): void {}
    public function findComparisonReportByUuid(string $uuid): ?ComparisonReport { return null; }
    public function findComparisonReportByBaselineAndFollowUp(string $baselineAssessmentUuid, string $followUpAssessmentUuid): ?ComparisonReport { return null; }
    public function findComparisonReportsByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array { return []; }
}

final class AllowAllAiAssessmentPermissions implements IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void {}
}
