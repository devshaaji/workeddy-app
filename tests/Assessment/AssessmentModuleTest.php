<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Assessment;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Assessment\Application\AttachAssessmentVideoUseCase;
use WorkEddy\Modules\Assessment\Application\CreateManualAssessmentUseCase;
use WorkEddy\Modules\Assessment\Application\GenerateComparisonReportUseCase;
use WorkEddy\Modules\Assessment\Application\GetAssessmentUseCase;
use WorkEddy\Modules\Assessment\Application\GetComparisonReportUseCase;
use WorkEddy\Modules\Assessment\Application\ListComparisonReportsUseCase;
use WorkEddy\Modules\Assessment\Application\LockComparisonReportUseCase;
use WorkEddy\Modules\Assessment\Application\MarkAssessmentBaselineUseCase;
use WorkEddy\Modules\Assessment\Application\ReviewAssessmentUseCase;
use WorkEddy\Modules\Assessment\Application\SubmitAssessmentForReviewUseCase;
use WorkEddy\Modules\Assessment\Application\Services\AssessmentComparisonService;
use WorkEddy\Modules\Assessment\Application\Services\ImprovementProofService;
use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Privacy\Authorization\PrivacyPermissions;
use WorkEddy\Modules\Reporting\Authorization\ReportingPermissions;
use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\AiScoreOutput;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;
use WorkEddy\Modules\Assessment\Domain\ComparisonReport;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveAction;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionLibraryItem;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionRecommendation;
use WorkEddy\Modules\CorrectiveAction\Domain\RecommendationRule;
use WorkEddy\Modules\Assessment\Settings\AssessmentSettings;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\Ergonomics\Domain\Services\NioshService;
use WorkEddy\Modules\Ergonomics\Domain\Services\RebaService;
use WorkEddy\Modules\Ergonomics\Domain\Services\RulaService;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Organization;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionLimitGuard;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRecorder;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionUsage;
use WorkEddy\Modules\Subscription\Domain\ValueObjects\SubscriptionLimits;
use WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository;
use WorkEddy\Modules\Task\Domain\Task;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\ModuleSettings;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\ValidationException;

final class AssessmentModuleTest extends TestCase
{
    public function test_service_provider_exposes_settings_and_permissions(): void
    {
        $provider = new \WorkEddy\Modules\Assessment\ServiceProvider();

        self::assertSame('assessment', $provider->getName());
        self::assertNotNull($provider->getSettingsProvider());
        self::assertSame('assessment', $provider->getSettingsProvider()?->getModuleName());
        self::assertNotSame([], $provider->getSettingsProvider()?->getDefinitions());
        self::assertNotNull($provider->getPermissionDefinitionProvider());
        self::assertSame('assessment', $provider->getPermissionDefinitionProvider()?->module());
        self::assertTrue(is_subclass_of(AssessmentSettings::class, ModuleSettings::class));
    }

    public function test_manual_assessment_video_and_review_workflow(): void
    {
        $organization = new Organization(
            id: 3,
            uuid: '11111111-1111-4111-8111-111111111111',
            name: 'Acme Safety Group',
            slug: 'acme',
            status: 'active',
            contactEmail: null,
            phone: null,
            createdAt: '2026-07-07 00:00:00',
        );
        $task = new Task(
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
        $assessments = new InMemoryAssessmentRepository();
        $audit = new RecordingAssessmentAuditService();
        $actor = new UserContext(
            userId: 44,
            organizationId: 3,
            organizationUuid: $organization->getUuid(),
            roleType: 'staff',
            privileges: [
                AssessmentPermissions::CREATE,
                AssessmentPermissions::UPDATE,
                AssessmentPermissions::VIEW,
                AssessmentPermissions::REVIEW,
                AssessmentPermissions::LOCK,
                AssessmentPermissions::VIDEO_UPLOAD,
            ],
        );

        $engine = new AssessmentEngine([
            new RebaService(),
            new RulaService(),
            new NioshService(),
        ]);
        $organizations = new SingleAssessmentOrganizationRepository($organization);
        $tasks = new SingleAssessmentTaskRepository($task);
        $permissions = new AllowAllAssessmentPermissionService();
        $tx = new PassthroughAssessmentTransactionManager();

        $create = new CreateManualAssessmentUseCase($organizations, $tasks, $assessments, $engine, $permissions, $tx, $audit, new AssessmentAllowingLimitGuard(), new AssessmentRecordingUsageRecorder());
        $submit = new SubmitAssessmentForReviewUseCase($assessments, $permissions, $tx, $audit);
        $attachVideo = new AttachAssessmentVideoUseCase($assessments, $permissions, $tx, $audit);
        $review = new ReviewAssessmentUseCase($assessments, $permissions, $tx, $audit);

        $created = $create->execute(
            organizationUuid: $organization->getUuid(),
            taskUuid: $task->getUuid(),
            model: 'reba',
            metrics: [
                'trunk_angle' => 70,
                'neck_angle' => 30,
                'upper_arm_angle' => 100,
                'lower_arm_angle' => 40,
                'wrist_angle' => 30,
                'leg_score' => 2,
                'load_weight' => 12,
                'coupling' => 'poor',
            ],
            actor: $actor,
            riskFactors: ['forceful_exertion', 'awkward_posture'],
            bodyRegions: [
                ['region' => 'lower_back', 'side' => 'back', 'intensity' => 4],
            ],
        );

        self::assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $created['uuid']);
        self::assertSame('pending_review', $created['status']);
        self::assertSame('manual', $created['scoreSource']);
        self::assertSame('11111111-1111-4111-8111-111111111111', $created['organizationUuid']);
        self::assertSame('22222222-2222-4222-8222-222222222222', $created['taskUuid']);
        self::assertSame('lower_back', $created['bodyRegions'][0]['region']);
        self::assertStringContainsString('lower_back', $created['bodyRegionHeatmap']['backSvg']);

        $video = $attachVideo->execute(
            assessmentUuid: $created['uuid'],
            actor: $actor,
            storageFileUuid: '33333333-3333-4333-8333-333333333333',
            originalFilename: 'lift.mp4',
            mimeType: 'video/mp4',
            sizeBytes: 1048576,
            durationSeconds: 42,
            consentTextVersion: 'workeddy-video-consent-v1',
            faceBlurRequested: true,
        );
        self::assertSame('33333333-3333-4333-8333-333333333333', $video['storageFileUuid']);
        self::assertSame('pending', $video['processingStatus']);
        self::assertTrue($video['faceBlurRequested']);

        $reviewed = $review->approve(
            assessmentUuid: $created['uuid'],
            actor: $actor,
            reviewerName: 'Dr Reviewer',
            reviewerCredentials: 'CPE',
            reviewerNotes: 'Reviewed against posture evidence.',
            adjustedScore: 12.0,
            adjustmentReason: 'Reviewer confirmed a slightly lower exposure level.',
            lock: true,
        );

        self::assertSame('locked', $reviewed['status']);
        self::assertSame('reviewer_confirmed', $reviewed['scoreSource']);
        self::assertSame(12.0, $reviewed['finalScore']['raw']);
        self::assertSame('Dr Reviewer', $reviewed['review']['reviewerName']);
        self::assertSame(['assessment.created', 'assessment.submitted', 'assessment.video.attached', 'assessment.reviewed'], array_column($audit->records, 'action'));
    }

    public function test_manual_assessment_can_be_saved_as_draft_explicitly(): void
    {
        $organization = new Organization(
            id: 3,
            uuid: '11111111-1111-4111-8111-111111111111',
            name: 'Acme Safety Group',
            slug: 'acme',
            status: 'active',
            contactEmail: null,
            phone: null,
            createdAt: '2026-07-07 00:00:00',
        );
        $task = new Task(
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
        $assessments = new InMemoryAssessmentRepository();
        $audit = new RecordingAssessmentAuditService();
        $actor = new UserContext(
            userId: 44,
            organizationId: 3,
            organizationUuid: $organization->getUuid(),
            roleType: 'staff',
            privileges: [AssessmentPermissions::CREATE],
        );

        $create = new CreateManualAssessmentUseCase(
            new SingleAssessmentOrganizationRepository($organization),
            new SingleAssessmentTaskRepository($task),
            $assessments,
            new AssessmentEngine([new RebaService(), new RulaService(), new NioshService()]),
            new AllowAllAssessmentPermissionService(),
            new PassthroughAssessmentTransactionManager(),
            $audit,
            new AssessmentAllowingLimitGuard(),
            new AssessmentRecordingUsageRecorder(),
        );

        $created = $create->execute(
            organizationUuid: $organization->getUuid(),
            taskUuid: $task->getUuid(),
            model: 'reba',
            metrics: [
                'trunk_angle' => 40,
                'neck_angle' => 20,
                'upper_arm_angle' => 50,
                'lower_arm_angle' => 80,
                'wrist_angle' => 15,
                'leg_score' => 2,
                'load_weight' => 8,
                'coupling' => 'fair',
            ],
            actor: $actor,
            submitForReview: false,
        );

        self::assertSame('draft', $created['status']);
        self::assertSame(['assessment.created'], array_column($audit->records, 'action'));
    }

    public function test_reviewer_can_flag_pending_assessment_with_notes(): void
    {
        $assessment = Assessment::create(
            id: 1,
            uuid: '55555555-5555-4555-8555-555555555555',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: [],
            initialScore: ['raw_score' => 8.0, 'normalized_score' => 53.33, 'risk_level' => 'High', 'risk_category' => 'high'],
            riskFactors: [],
            bodyRegions: [],
            createdBy: 44,
        )->markSubmitted();
        $repo = new InMemoryAssessmentRepository([$assessment]);
        $audit = new RecordingAssessmentAuditService();
        $useCase = new ReviewAssessmentUseCase(
            $repo,
            new AllowAllAssessmentPermissionService(),
            new PassthroughAssessmentTransactionManager(),
            $audit,
        );

        $flagged = $useCase->flag(
            assessmentUuid: $assessment->getUuid(),
            actor: new UserContext(userId: 44, organizationId: 3, roleType: 'staff', privileges: [AssessmentPermissions::REVIEW]),
            reviewerName: 'Dr Reviewer',
            reviewerNotes: 'Video angle is insufficient for final approval.',
            reviewerCredentials: 'CPE',
        );

        self::assertSame('flagged', $flagged['status']);
        self::assertSame('Video angle is insufficient for final approval.', $flagged['review']['reviewerNotes']);
        self::assertSame(['assessment.flagged'], array_column($audit->records, 'action'));
    }

    public function test_locked_assessment_rejects_video_attachment(): void
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
            initialScore: ['raw_score' => 8.0, 'normalized_score' => 53.33, 'risk_level' => 'High', 'risk_category' => 'high'],
            riskFactors: [],
            bodyRegions: [],
            createdBy: 44,
        )->markSubmitted()->markReviewed(
            reviewerId: 44,
            reviewerName: 'Dr Reviewer',
            reviewerCredentials: 'CPE',
            reviewerNotes: null,
            finalScore: ['raw_score' => 8.0, 'normalized_score' => 53.33, 'risk_level' => 'High', 'risk_category' => 'high'],
            adjustmentReason: null,
            lock: true,
        );
        $repo = new InMemoryAssessmentRepository([$assessment]);
        $useCase = new AttachAssessmentVideoUseCase(
            $repo,
            new AllowAllAssessmentPermissionService(),
            new PassthroughAssessmentTransactionManager(),
            new RecordingAssessmentAuditService(),
        );

        $this->expectException(ValidationException::class);

        $useCase->execute(
            assessmentUuid: $assessment->getUuid(),
            actor: new UserContext(userId: 44, organizationId: 3, roleType: 'staff', privileges: [AssessmentPermissions::VIDEO_UPLOAD]),
            storageFileUuid: '33333333-3333-4333-8333-333333333333',
            originalFilename: 'lift.mp4',
            mimeType: 'video/mp4',
            sizeBytes: 1048576,
            durationSeconds: 42,
            consentTextVersion: 'workeddy-video-consent-v1',
            faceBlurRequested: false,
        );
    }

    public function test_baseline_marking_persists_and_locks_reviewed_assessment(): void
    {
        $assessment = Assessment::create(
            id: 1,
            uuid: '66666666-6666-4666-8666-666666666666',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: [],
            initialScore: ['raw_score' => 8.0, 'normalized_score' => 53.33, 'risk_level' => 'High', 'risk_category' => 'high'],
            riskFactors: ['forceful_exertion'],
            bodyRegions: [['region' => 'lower_back', 'side' => 'back', 'intensity' => 4]],
            createdBy: 44,
        )->markSubmitted()->markReviewed(
            reviewerId: 44,
            reviewerName: 'Dr Reviewer',
            reviewerCredentials: 'CPE',
            reviewerNotes: 'Ready for baseline.',
            finalScore: ['raw_score' => 8.0, 'normalized_score' => 53.33, 'risk_level' => 'High', 'risk_category' => 'high'],
            adjustmentReason: null,
            lock: false,
        );
        $repo = new InMemoryAssessmentRepository([$assessment]);
        $audit = new RecordingAssessmentAuditService();
        $useCase = new MarkAssessmentBaselineUseCase($repo, new AllowAllAssessmentPermissionService(), new PassthroughAssessmentTransactionManager(), $audit);

        $result = $useCase->execute($assessment->getUuid(), $this->actorWithUpdate());

        self::assertTrue($result['isBaseline']);
        self::assertSame('locked', $result['status']);
        self::assertTrue($repo->findByUuid($assessment->getUuid())?->isBaseline());
        self::assertSame(['assessment.baseline_marked'], array_column($audit->records, 'action'));
    }

    public function test_draft_assessment_cannot_become_baseline(): void
    {
        $draft = Assessment::create(
            id: 1,
            uuid: '77777777-7777-4777-8777-777777777777',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: [],
            initialScore: ['raw_score' => 6.0, 'normalized_score' => 40.0, 'risk_level' => 'Medium', 'risk_category' => 'medium'],
            riskFactors: [],
            bodyRegions: [],
            createdBy: 44,
        );
        $useCase = new MarkAssessmentBaselineUseCase(
            new InMemoryAssessmentRepository([$draft]),
            new AllowAllAssessmentPermissionService(),
            new PassthroughAssessmentTransactionManager(),
            new RecordingAssessmentAuditService(),
        );

        $this->expectException(ValidationException::class);
        $useCase->execute($draft->getUuid(), $this->actorWithUpdate());
    }

    public function test_get_assessment_returns_action_flags_and_baseline_state(): void
    {
        $assessment = Assessment::create(
            id: 1,
            uuid: '88888888-8888-4888-8888-888888888888',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: [],
            initialScore: ['raw_score' => 7.0, 'normalized_score' => 46.7, 'risk_level' => 'Medium', 'risk_category' => 'medium'],
            riskFactors: [],
            bodyRegions: [],
            createdBy: 44,
        )->markSubmitted()->markReviewed(
            reviewerId: 44,
            reviewerName: 'Dr Reviewer',
            reviewerCredentials: 'CPE',
            reviewerNotes: 'Finalized.',
            finalScore: ['raw_score' => 7.0, 'normalized_score' => 46.7, 'risk_level' => 'Medium', 'risk_category' => 'medium'],
            adjustmentReason: null,
            lock: true,
        );
        $repo = new InMemoryAssessmentRepository([$assessment]);
        $baseline = new MarkAssessmentBaselineUseCase($repo, new AllowAllAssessmentPermissionService(), new PassthroughAssessmentTransactionManager(), new RecordingAssessmentAuditService());
        $baseline->execute($assessment->getUuid(), $this->actorWithUpdate());

        $view = (new GetAssessmentUseCase($repo, new AllowAllAssessmentPermissionService()))
            ->execute($assessment->getUuid(), $this->actorWithView());

        self::assertArrayHasKey('actions', $view);
        self::assertArrayHasKey('isBaseline', $view);
        self::assertTrue($view['isBaseline']);
    }

    public function test_get_assessment_returns_video_assets_status_rail_and_reports(): void
    {
        $assessment = Assessment::create(
            id: 1,
            uuid: '12121212-1212-4212-8212-121212121212',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: [],
            initialScore: ['raw_score' => 7.0, 'normalized_score' => 46.7, 'risk_level' => 'Medium', 'risk_category' => 'medium'],
            riskFactors: [],
            bodyRegions: [],
            createdBy: 44,
        )->withVideo((new AssessmentVideo(
            id: 1,
            uuid: '13131313-1313-4313-8313-131313131313',
            assessmentId: 1,
            storageFileUuid: '33333333-3333-4333-8333-333333333333',
            originalFilename: 'lift.mp4',
            mimeType: 'video/mp4',
            sizeBytes: 1048576,
            durationSeconds: 42,
            consentTextVersion: 'workeddy-video-consent-v1',
            faceBlurRequested: true,
            processingStatus: 'completed',
            processingStartedAt: '2026-07-07 10:05:00',
            processingCompletedAt: '2026-07-07 10:06:00',
            processingError: null,
            thumbnailStorageFileUuid: '44444444-4444-4444-8444-444444444444',
            poseVideoStorageFileUuid: '55555555-5555-4555-8555-555555555555',
            facesBlurred: true,
            processingConfidence: 0.91,
            createdAt: '2026-07-07 10:00:00',
        ))->withBlurredStorageFileUuid('66666666-6666-4666-8666-666666666666'));
        $repo = new InMemoryAssessmentRepository([$assessment]);
        $repo->createComparisonReport(new ComparisonReport(
            id: null,
            uuid: '14141414-1414-4414-8414-141414141414',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            baselineAssessmentUuid: $assessment->getUuid(),
            followUpAssessmentUuid: '15151515-1515-4515-8515-151515151515',
            correctiveActionUuid: '16161616-1616-4616-8616-161616161616',
            model: 'reba',
            baselineScore: ['raw' => 7.0],
            followUpScore: ['raw' => 4.0],
            scoreDiff: ['raw' => -3.0],
            riskReductionPercent: 42.0,
            direction: 'improved',
            bodyRegionsImproved: [],
            bodyRegionsWorsened: [],
            evidenceChain: [],
            status: 'generated',
            generatedBy: 44,
            generatedAt: '2026-07-07 10:10:00',
            lockedAt: null,
            createdAt: '2026-07-07 10:10:00',
        ));

        $view = (new GetAssessmentUseCase($repo, new AllowAllAssessmentPermissionService()))
            ->execute($assessment->getUuid(), $this->actorWithEvidenceView());

        self::assertArrayHasKey('statusRail', $view);
        self::assertSame('captured', $view['statusRail']['consentStatus']);
        self::assertSame('completed', $view['statusRail']['processingStatus']);
        self::assertSame('blurred', $view['statusRail']['blurStatus']);
        self::assertArrayHasKey('videoAssets', $view);
        self::assertSame(['blurred_video', 'pose_video', 'thumbnail', 'original_video'], array_column($view['videoAssets'], 'assetType'));
        self::assertTrue($view['videoAssets'][0]['actions']['canRequestSignedAccess']);
        self::assertTrue($view['videoAssets'][0]['actions']['canViewOriginal']);
        self::assertTrue($view['videoAssets'][1]['actions']['canViewBlurred']);
        self::assertArrayHasKey('reporting', $view);
        self::assertSame(
            ['assessment_report', 'comparison_report', 'corrective_action_report'],
            array_column($view['reporting']['reports'], 'reportType'),
        );
        self::assertStringNotContainsString('storage://', json_encode($view, JSON_THROW_ON_ERROR));
    }

    public function test_video_evidence_view_is_review_only(): void
    {
        $routeParams = ['assessmentId' => '17171717-1717-4717-8717-171717171717'];
        $organizationUuid = '11111111-1111-4111-8111-111111111111';

        ob_start();
        require dirname(__DIR__, 2) . '/modules/Assessment/Presentation/Views/video_evidence.php';
        $html = (string) ob_get_clean();

        self::assertStringContainsString('assessmentVideoEvidencePage', $html);
        self::assertStringContainsString('Evidence', $html);
        self::assertStringContainsString('Processing Outputs', $html);
        self::assertStringContainsString('Reports', $html);
        self::assertStringNotContainsString('type="file"', $html);
        self::assertStringNotContainsString('upload-and-process', $html);
    }

    public function test_generate_list_get_and_lock_comparison_report_workflow(): void
    {
        $baseline = Assessment::create(
            id: 1,
            uuid: '99999999-9999-4999-8999-999999999999',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: ['trunk_angle' => 50, 'neck_angle' => 20],
            initialScore: ['raw_score' => 9.0, 'normalized_score' => 60.0, 'risk_level' => 'High', 'risk_category' => 'high'],
            riskFactors: ['awkward_posture'],
            bodyRegions: [['region' => 'lower_back', 'side' => 'back', 'intensity' => 4]],
            createdBy: 44,
        )->markSubmitted()->markReviewed(
            reviewerId: 44,
            reviewerName: 'Dr Reviewer',
            reviewerCredentials: 'CPE',
            reviewerNotes: 'Locked baseline.',
            finalScore: ['raw_score' => 9.0, 'normalized_score' => 60.0, 'risk_level' => 'High', 'risk_category' => 'high'],
            adjustmentReason: null,
            lock: true,
        )->markBaseline();
        $followUp = Assessment::create(
            id: 2,
            uuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: ['trunk_angle' => 20, 'neck_angle' => 8],
            initialScore: ['raw_score' => 4.0, 'normalized_score' => 26.7, 'risk_level' => 'Low', 'risk_category' => 'low'],
            riskFactors: [],
            bodyRegions: [['region' => 'lower_back', 'side' => 'back', 'intensity' => 1]],
            createdBy: 44,
        )->markSubmitted()->markReviewed(
            reviewerId: 44,
            reviewerName: 'Dr Reviewer',
            reviewerCredentials: 'CPE',
            reviewerNotes: 'Follow-up ready.',
            finalScore: ['raw_score' => 4.0, 'normalized_score' => 26.7, 'risk_level' => 'Low', 'risk_category' => 'low'],
            adjustmentReason: null,
            lock: false,
        );

        $repo = new InMemoryAssessmentRepository([$baseline, $followUp]);
        $audit = new RecordingAssessmentAuditService();
        $actor = new UserContext(
            userId: 44,
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            roleType: 'staff',
            privileges: [
                AssessmentPermissions::GENERATE_COMPARISON,
                AssessmentPermissions::VIEW_COMPARISON,
                AssessmentPermissions::LOCK_COMPARISON,
            ],
        );

        $generate = new GenerateComparisonReportUseCase(
            $repo,
            new AssessmentComparisonService(new ImprovementProofService()),
            new AllowAllAssessmentPermissionService(),
            new PassthroughAssessmentTransactionManager(),
            $audit,
        );
        $list = new ListComparisonReportsUseCase($repo, new AllowAllAssessmentPermissionService());
        $get = new GetComparisonReportUseCase($repo, new AllowAllAssessmentPermissionService());
        $lock = new LockComparisonReportUseCase($repo, new AllowAllAssessmentPermissionService(), new PassthroughAssessmentTransactionManager(), $audit);

        $generated = $generate->execute($baseline->getUuid(), $followUp->getUuid(), $actor);
        self::assertSame('generated', $generated['status']);
        self::assertSame('improved', $generated['direction']);
        self::assertGreaterThan(0, $generated['riskReductionPercent']);
        self::assertCount(1, $generated['bodyRegionsImproved']);
        self::assertArrayHasKey('bodyRegionHeatmap', $generated['evidenceChain']['baseline']);
        self::assertArrayHasKey('frontSvg', $generated['evidenceChain']['baseline']['bodyRegionHeatmap']);
        self::assertArrayHasKey('bodyRegionHeatmap', $generated['evidenceChain']['followUp']);

        $listed = $list->execute($actor);
        self::assertCount(1, $listed);
        self::assertSame($generated['uuid'], $listed[0]['uuid']);

        $loaded = $get->execute($generated['uuid'], $actor);
        self::assertSame($generated['uuid'], $loaded['uuid']);

        $locked = $lock->execute($generated['uuid'], $actor);
        self::assertSame('locked', $locked['status']);
        self::assertNotNull($locked['lockedAt']);
        self::assertSame(
            ['comparison_report.generated', 'comparison_report.locked'],
            array_slice(array_column($audit->records, 'action'), -2),
        );
    }

    public function test_generate_comparison_links_verified_corrective_action(): void
    {
        [$baseline, $followUp] = $this->comparisonAssessments();
        $repo = new InMemoryAssessmentRepository([$baseline, $followUp]);
        $action = new CorrectiveAction(
            id: 1,
            uuid: 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            assessmentUuid: $baseline->getUuid(),
            recommendationUuid: null,
            libraryItemUuid: null,
            title: 'Install lift table',
            description: null,
            reason: null,
            controlType: 'permanent',
            hierarchyLevel: 'engineering',
            priority: 'high',
            status: 'verified',
            assignedToUserId: 55,
            assignedByUserId: 44,
            dueDate: '2026-08-01',
            followUpAssessmentDueDate: null,
            evidenceRequirements: [],
            rejectReason: null,
            completedAt: '2026-08-05 10:00:00',
            verifiedAt: '2026-08-06 10:00:00',
        );

        $useCase = new GenerateComparisonReportUseCase(
            $repo,
            new AssessmentComparisonService(new ImprovementProofService()),
            new AllowAllAssessmentPermissionService(),
            new PassthroughAssessmentTransactionManager(),
            new RecordingAssessmentAuditService(),
            new InMemoryComparisonCorrectiveActionRepository([$action]),
        );

        $generated = $useCase->execute(
            $baseline->getUuid(),
            $followUp->getUuid(),
            new UserContext(userId: 44, organizationId: 3, organizationUuid: '11111111-1111-4111-8111-111111111111', roleType: 'staff', privileges: [AssessmentPermissions::GENERATE_COMPARISON]),
            $action->uuid,
        );

        self::assertSame($action->uuid, $generated['correctiveActionUuid']);
        self::assertSame('verified', $generated['evidenceChain']['correctiveAction']['status']);
    }

    public function test_generate_comparison_rejects_unverified_corrective_action(): void
    {
        [$baseline, $followUp] = $this->comparisonAssessments();
        $action = new CorrectiveAction(
            id: 1,
            uuid: 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            assessmentUuid: $baseline->getUuid(),
            recommendationUuid: null,
            libraryItemUuid: null,
            title: 'Install lift table',
            description: null,
            reason: null,
            controlType: 'permanent',
            hierarchyLevel: 'engineering',
            priority: 'high',
            status: 'completed',
            assignedToUserId: 55,
            assignedByUserId: 44,
            dueDate: '2026-08-01',
            followUpAssessmentDueDate: null,
            evidenceRequirements: [],
            rejectReason: null,
            completedAt: '2026-08-05 10:00:00',
        );

        $useCase = new GenerateComparisonReportUseCase(
            new InMemoryAssessmentRepository([$baseline, $followUp]),
            new AssessmentComparisonService(new ImprovementProofService()),
            new AllowAllAssessmentPermissionService(),
            new PassthroughAssessmentTransactionManager(),
            new RecordingAssessmentAuditService(),
            new InMemoryComparisonCorrectiveActionRepository([$action]),
        );

        $this->expectException(ValidationException::class);
        $useCase->execute(
            $baseline->getUuid(),
            $followUp->getUuid(),
            new UserContext(userId: 44, organizationId: 3, organizationUuid: '11111111-1111-4111-8111-111111111111', roleType: 'staff', privileges: [AssessmentPermissions::GENERATE_COMPARISON]),
            $action->uuid,
        );
    }

    public function test_generate_comparison_rejects_model_mismatch(): void
    {
        $baseline = Assessment::create(
            id: 1,
            uuid: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: [],
            initialScore: ['raw_score' => 8.0, 'normalized_score' => 53.33, 'risk_level' => 'High', 'risk_category' => 'high'],
            riskFactors: [],
            bodyRegions: [],
            createdBy: 44,
        )->markSubmitted()->markReviewed(
            reviewerId: 44,
            reviewerName: 'Dr Reviewer',
            reviewerCredentials: 'CPE',
            reviewerNotes: 'Baseline',
            finalScore: ['raw_score' => 8.0, 'normalized_score' => 53.33, 'risk_level' => 'High', 'risk_category' => 'high'],
            adjustmentReason: null,
            lock: true,
        )->markBaseline();
        $followUp = Assessment::create(
            id: 2,
            uuid: 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'rula',
            metrics: [],
            initialScore: ['raw_score' => 4.0, 'normalized_score' => 26.7, 'risk_level' => 'Low', 'risk_category' => 'low'],
            riskFactors: [],
            bodyRegions: [],
            createdBy: 44,
        )->markSubmitted()->markReviewed(
            reviewerId: 44,
            reviewerName: 'Dr Reviewer',
            reviewerCredentials: 'CPE',
            reviewerNotes: 'Follow-up',
            finalScore: ['raw_score' => 4.0, 'normalized_score' => 26.7, 'risk_level' => 'Low', 'risk_category' => 'low'],
            adjustmentReason: null,
            lock: false,
        );

        $useCase = new GenerateComparisonReportUseCase(
            new InMemoryAssessmentRepository([$baseline, $followUp]),
            new AssessmentComparisonService(new ImprovementProofService()),
            new AllowAllAssessmentPermissionService(),
            new PassthroughAssessmentTransactionManager(),
            new RecordingAssessmentAuditService(),
        );

        $this->expectException(\RuntimeException::class);
        $useCase->execute(
            $baseline->getUuid(),
            $followUp->getUuid(),
            new UserContext(userId: 44, organizationId: 3, organizationUuid: '11111111-1111-4111-8111-111111111111', roleType: 'staff', privileges: [AssessmentPermissions::GENERATE_COMPARISON]),
        );
    }

    /** @return array{0:Assessment,1:Assessment} */
    private function comparisonAssessments(): array
    {
        $baseline = Assessment::create(
            id: 1,
            uuid: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: ['trunk_angle' => 60],
            initialScore: ['raw_score' => 8.0, 'normalized_score' => 53.33, 'risk_level' => 'High', 'risk_category' => 'high'],
            riskFactors: [],
            bodyRegions: [['region' => 'lower_back', 'side' => 'back', 'intensity' => 4]],
            createdBy: 44,
        )->markSubmitted()->markReviewed(
            reviewerId: 44,
            reviewerName: 'Dr Reviewer',
            reviewerCredentials: 'CPE',
            reviewerNotes: 'Baseline',
            finalScore: ['raw_score' => 8.0, 'normalized_score' => 53.33, 'risk_level' => 'High', 'risk_category' => 'high'],
            adjustmentReason: null,
            lock: true,
        )->markBaseline();

        $followUp = Assessment::create(
            id: 2,
            uuid: 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: ['trunk_angle' => 20],
            initialScore: ['raw_score' => 4.0, 'normalized_score' => 26.7, 'risk_level' => 'Low', 'risk_category' => 'low'],
            riskFactors: [],
            bodyRegions: [['region' => 'lower_back', 'side' => 'back', 'intensity' => 1]],
            createdBy: 44,
        )->markSubmitted()->markReviewed(
            reviewerId: 44,
            reviewerName: 'Dr Reviewer',
            reviewerCredentials: 'CPE',
            reviewerNotes: 'Follow-up',
            finalScore: ['raw_score' => 4.0, 'normalized_score' => 26.7, 'risk_level' => 'Low', 'risk_category' => 'low'],
            adjustmentReason: null,
            lock: false,
        );

        return [$baseline, $followUp];
    }

    private function actorWithUpdate(): UserContext
    {
        return new UserContext(
            userId: 44,
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            roleType: 'staff',
            privileges: [AssessmentPermissions::UPDATE, AssessmentPermissions::VIEW],
        );
    }

    private function actorWithView(): UserContext
    {
        return new UserContext(
            userId: 44,
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            roleType: 'staff',
            privileges: [AssessmentPermissions::VIEW],
        );
    }

    private function actorWithEvidenceView(): UserContext
    {
        return new UserContext(
            userId: 44,
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            roleType: 'staff',
            privileges: [
                AssessmentPermissions::VIEW,
                PrivacyPermissions::VIDEO_ACCESS,
                PrivacyPermissions::AUDIT_VIEW,
                ReportingPermissions::VIEW,
            ],
        );
    }
}

final class AssessmentAllowingLimitGuard implements ISubscriptionLimitGuard
{
    public function forOrganization(int $organizationId, string $metric): SubscriptionLimits
    {
        return SubscriptionLimits::fromValues($metric, 100, 0);
    }

    public function wouldExceed(int $organizationId, string $metric, int $increment = 1): bool
    {
        return false;
    }
}

final class AssessmentRecordingUsageRecorder implements ISubscriptionUsageRecorder
{
    public function forOrganization(int $organizationId, string $metric, int $increment = 1): SubscriptionUsage
    {
        return new SubscriptionUsage(
            subscriptionUuid: 'sub-assessment',
            periodStart: new \DateTimeImmutable('2026-07-01'),
            periodEnd: new \DateTimeImmutable('2026-07-31'),
            usageData: [$metric => $increment],
            updatedAt: new \DateTimeImmutable('2026-07-08 10:00:00'),
        );
    }
}

final class SingleAssessmentOrganizationRepository implements IOrganizationRepository
{
    public function __construct(private readonly Organization $organization) {}

    public function create(Organization $organization): int { return (int) $organization->getId(); }
    public function update(Organization $organization): void {}
    public function findById(int $id): ?Organization { return $id === $this->organization->getId() ? $this->organization : null; }
    public function findByUuid(string $uuid): ?Organization { return $uuid === $this->organization->getUuid() ? $this->organization : null; }
    public function findBySlug(string $slug): ?Organization { return $slug === $this->organization->getSlug() ? $this->organization : null; }
    public function findAll(int $limit = 50, int $offset = 0): array { return [$this->organization]; }
    public function softDelete(string $uuid): void {}
}

final class SingleAssessmentTaskRepository implements ITaskRepository
{
    public function __construct(private readonly Task $task) {}

    public function create(Task $task): int { return (int) $task->getId(); }
    public function update(Task $task): void {}
    public function delete(string $uuid): void {}
    public function findByUuid(string $uuid): ?Task { return $uuid === $this->task->getUuid() ? $this->task : null; }
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array { return $organizationId === $this->task->getOrganizationId() ? [$this->task] : []; }
}

final class InMemoryAssessmentRepository implements IAssessmentRepository
{
    /** @var array<string, Assessment> */
    private array $assessments = [];
    /** @var array<string, ComparisonReport> */
    private array $comparisonReports = [];
    /** @var array<string, list<AiScoreOutput>> */
    private array $aiOutputs = [];
    private int $nextId = 1;
    private int $nextComparisonId = 1;

    /**
     * @param list<Assessment> $assessments
     */
    public function __construct(array $assessments = [])
    {
        foreach ($assessments as $assessment) {
            $this->assessments[$assessment->getUuid()] = $assessment;
        }
    }

    public function create(Assessment $assessment): int
    {
        $id = $assessment->getId() ?? $this->nextId++;
        $persisted = $assessment->withId($id);
        $this->assessments[$persisted->getUuid()] = $persisted;

        return $id;
    }

    public function update(Assessment $assessment): void
    {
        $this->assessments[$assessment->getUuid()] = $assessment;
    }

    public function addVideo(AssessmentVideo $video): int
    {
        $assessment = $this->findById($video->getAssessmentId());
        if ($assessment !== null) {
            $this->assessments[$assessment->getUuid()] = $assessment->withVideo($video->withId(1));
        }

        return 1;
    }

    public function updateVideoProcessing(AssessmentVideo $video): void
    {
        $assessment = $this->findById($video->getAssessmentId());
        if ($assessment !== null) {
            $this->assessments[$assessment->getUuid()] = $assessment->replaceVideo($video);
        }
    }

    public function saveVideoProcessingResult(array $result): void {}

    public function findReusableVideoProcessingResult(string $videoSha256, string $processingProfileHash): ?array
    {
        return null;
    }

    public function saveAiScoreOutput(AiScoreOutput $output): int
    {
        $bucket = $this->aiOutputs[$output->assessmentUuid] ?? [];
        $bucket[] = $output;
        $this->aiOutputs[$output->assessmentUuid] = $bucket;

        return count($bucket);
    }

    public function findLatestAiScoreOutput(string $assessmentUuid): ?AiScoreOutput
    {
        $bucket = $this->aiOutputs[$assessmentUuid] ?? [];

        return $bucket === [] ? null : $bucket[array_key_last($bucket)];
    }

    public function findByUuid(string $uuid): ?Assessment
    {
        return $this->assessments[$uuid] ?? null;
    }

    public function findById(int $id): ?Assessment
    {
        foreach ($this->assessments as $assessment) {
            if ($assessment->getId() === $id) {
                return $assessment;
            }
        }

        return null;
    }

    public function findAllByOrganizationId(?int $organizationId, int $limit = 50, int $offset = 0): array
    {
        $items = array_values(array_filter(
            $this->assessments,
            static fn(Assessment $assessment): bool => $organizationId === null || $assessment->getOrganizationId() === $organizationId,
        ));

        return array_slice($items, $offset, $limit);
    }

    public function createComparisonReport(ComparisonReport $report): int
    {
        $id = $report->id ?? $this->nextComparisonId++;
        $persisted = new ComparisonReport(
            id: $id,
            uuid: $report->uuid,
            organizationId: $report->organizationId,
            organizationUuid: $report->organizationUuid,
            baselineAssessmentUuid: $report->baselineAssessmentUuid,
            followUpAssessmentUuid: $report->followUpAssessmentUuid,
            correctiveActionUuid: $report->correctiveActionUuid,
            model: $report->model,
            baselineScore: $report->baselineScore,
            followUpScore: $report->followUpScore,
            scoreDiff: $report->scoreDiff,
            riskReductionPercent: $report->riskReductionPercent,
            direction: $report->direction,
            bodyRegionsImproved: $report->bodyRegionsImproved,
            bodyRegionsWorsened: $report->bodyRegionsWorsened,
            evidenceChain: $report->evidenceChain,
            status: $report->status,
            generatedBy: $report->generatedBy,
            generatedAt: $report->generatedAt,
            lockedAt: $report->lockedAt,
            createdAt: $report->createdAt,
        );
        $this->comparisonReports[$persisted->uuid] = $persisted;

        return $id;
    }

    public function updateComparisonReport(ComparisonReport $report): void
    {
        $this->comparisonReports[$report->uuid] = $report;
    }

    public function findComparisonReportByUuid(string $uuid): ?ComparisonReport
    {
        return $this->comparisonReports[$uuid] ?? null;
    }

    public function findComparisonReportByBaselineAndFollowUp(string $baselineAssessmentUuid, string $followUpAssessmentUuid): ?ComparisonReport
    {
        foreach ($this->comparisonReports as $report) {
            if ($report->baselineAssessmentUuid === $baselineAssessmentUuid && $report->followUpAssessmentUuid === $followUpAssessmentUuid) {
                return $report;
            }
        }

        return null;
    }

    public function findComparisonReportsByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $items = array_values(array_filter($this->comparisonReports, static function (ComparisonReport $report) use ($organizationId, $filters): bool {
            if ($report->organizationId !== $organizationId) {
                return false;
            }
            if (($filters['baselineAssessmentUuid'] ?? null) && $report->baselineAssessmentUuid !== $filters['baselineAssessmentUuid']) {
                return false;
            }
            if (($filters['followUpAssessmentUuid'] ?? null) && $report->followUpAssessmentUuid !== $filters['followUpAssessmentUuid']) {
                return false;
            }
            if (($filters['status'] ?? null) && $report->status !== $filters['status']) {
                return false;
            }

            return true;
        }));

        return array_slice($items, $offset, $limit);
    }
}

final class InMemoryComparisonCorrectiveActionRepository implements ICorrectiveActionRepository
{
    /** @var array<string, CorrectiveAction> */
    private array $actions = [];

    /** @param list<CorrectiveAction> $actions */
    public function __construct(array $actions = [])
    {
        foreach ($actions as $action) {
            $this->actions[$action->uuid] = $action;
        }
    }

    public function replaceRecommendationsForAssessment(string $assessmentUuid, array $recommendations): void {}
    public function listRecommendationsByAssessment(string $assessmentUuid): array { return []; }
    public function findRecommendationByUuid(string $uuid): ?CorrectiveActionRecommendation { return null; }
    public function updateRecommendation(CorrectiveActionRecommendation $recommendation): void {}
    public function createAction(CorrectiveAction $action): int { $this->actions[$action->uuid] = $action; return 1; }
    public function updateAction(CorrectiveAction $action): void { $this->actions[$action->uuid] = $action; }
    public function findActionByUuid(string $uuid): ?CorrectiveAction { return $this->actions[$uuid] ?? null; }
    public function listActionsByOrganizationId(int $organizationId, array $filters = []): array { return array_values(array_filter($this->actions, static fn(CorrectiveAction $action): bool => $action->organizationId === $organizationId)); }
    public function addEvidence(array $data): array { return $data; }
    public function listEvidenceByActionUuid(string $actionUuid): array { return []; }
    public function addStatusHistory(array $data): void {}
    public function listStatusHistoryByActionUuid(string $actionUuid): array { return []; }
    public function upsertLibraryItem(CorrectiveActionLibraryItem $item): CorrectiveActionLibraryItem { return $item; }
    public function findLibraryItemByUuid(string $uuid): ?CorrectiveActionLibraryItem { return null; }
    public function countRulesForLibraryItem(string $libraryItemUuid): int { return 0; }
    public function listLibraryItems(array $filters = []): array { return []; }
    public function upsertRecommendationRule(RecommendationRule $rule): RecommendationRule { return $rule; }
    public function listRecommendationRules(array $filters = []): array { return []; }
    public function listDueActions(string $beforeDate, int $limit = 100): array { return []; }
    public function listDueFollowUps(string $beforeDate, int $limit = 100): array { return []; }
    public function createOrUpdateFollowUp(string $actionUuid, string $dueDate, ?string $followUpAssessmentUuid = null, string $status = 'scheduled'): void {}
    public function updateFollowUpStatus(string $actionUuid, string $status): void {}
}

final class AllowAllAssessmentPermissionService implements IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        if (!in_array($privilege, $ctx->privileges, true)) {
            throw new \RuntimeException('Missing expected privilege in test context: ' . $privilege);
        }
    }
}

final class PassthroughAssessmentTransactionManager implements TransactionManagerInterface
{
    public function transactional(callable $callback): mixed
    {
        return $callback();
    }
}

final class RecordingAssessmentAuditService implements IAuditService
{
    /** @var list<array<string, mixed>> */
    public array $records = [];

    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
        $this->records[] = compact('action', 'entityType', 'entityId', 'beforeState', 'afterState', 'actorId', 'actorType', 'idempotencyKey', 'metadata');
    }
}
