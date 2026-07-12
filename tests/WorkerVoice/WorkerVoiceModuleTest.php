<?php

declare(strict_types=1);

namespace WorkEddy\Tests\WorkerVoice;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\AiScoreOutput;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Organization\Domain\Department;
use WorkEddy\Modules\Organization\Domain\JobRole;
use WorkEddy\Modules\Organization\Domain\Worksite;
use WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository;
use WorkEddy\Modules\Task\Domain\Task;
use WorkEddy\Modules\WorkerVoice\Application\GetWorkerFeedbackTrendsUseCase;
use WorkEddy\Modules\WorkerVoice\Application\GetWorkerFeedbackUseCase;
use WorkEddy\Modules\WorkerVoice\Application\ListWorkerFeedbackUseCase;
use WorkEddy\Modules\WorkerVoice\Application\Services\WorkerFeedbackTrendService;
use WorkEddy\Modules\WorkerVoice\Application\Services\WorkerFeedbackViewService;
use WorkEddy\Modules\WorkerVoice\Application\SubmitWorkerFeedbackUseCase;
use WorkEddy\Modules\WorkerVoice\Authorization\WorkerVoicePermissions;
use WorkEddy\Modules\WorkerVoice\Domain\Contracts\IWorkerVoiceRepository;
use WorkEddy\Modules\WorkerVoice\Domain\WorkerFeedback;
use WorkEddy\Modules\WorkerVoice\Settings\WorkerVoiceSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\ModuleSettings;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class WorkerVoiceModuleTest extends TestCase
{
    public function test_index_view_exposes_advanced_table_targets_used_by_register_script(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/modules/WorkerVoice/Presentation/Views/index.php');
        $script = file_get_contents($root . '/public/assets/js/modules/worker-voice-index.js');

        self::assertIsString($view);
        self::assertIsString($script);
        self::assertStringContainsString('workerVoiceIndexPage', $view);
        self::assertStringContainsString('workerVoiceIndexAlert', $view);
        self::assertStringContainsString('workerVoiceTableCard', $view);
        self::assertStringContainsString('workerVoiceSearchFilter', $view);
        self::assertStringContainsString('workerVoiceIndexTable', $view);
        self::assertStringContainsString('workerVoiceResultCount', $view);
        self::assertStringContainsString('workerVoicePagination', $view);
        self::assertStringContainsString('workerVoiceRefreshBtn', $view);
        self::assertStringNotContainsString('workerVoiceRegisterCount', $view);
        self::assertStringContainsString('App.tables.createAdvanced', $script);
        self::assertStringContainsString('workerVoiceSearchFilter', $script);
        self::assertStringContainsString('workerVoiceIndexTable', $script);
    }

    public function test_service_provider_exposes_settings_and_permissions(): void
    {
        $provider = new \WorkEddy\Modules\WorkerVoice\ServiceProvider();

        self::assertSame('worker_voice', $provider->getName());
        self::assertNotNull($provider->getSettingsProvider());
        self::assertSame('worker_voice', $provider->getSettingsProvider()?->getModuleName());
        self::assertNotSame([], $provider->getSettingsProvider()?->getDefinitions());
        self::assertNotNull($provider->getPermissionDefinitionProvider());
        self::assertSame('worker_voice', $provider->getPermissionDefinitionProvider()?->module());
        self::assertTrue(is_subclass_of(WorkerVoiceSettings::class, ModuleSettings::class));
        self::assertFileExists((string) $provider->getRouteFile());
        self::assertFileExists(dirname((string) $provider->getRouteFile()) . '/Views/index.php');
        self::assertFileExists(dirname((string) $provider->getRouteFile()) . '/Views/submit.php');
        self::assertFileExists(dirname((string) $provider->getRouteFile()) . '/Views/show.php');
        self::assertFileExists(dirname((string) $provider->getRouteFile()) . '/Views/trends.php');
    }

    public function test_submit_get_list_and_trends_redact_anonymous_identity(): void
    {
        $repo = new InMemoryWorkerVoiceRepository();
        $tasks = new SingleWorkerVoiceTaskRepository(new Task(
            id: 5,
            uuid: '22222222-2222-4222-8222-222222222222',
            organizationId: 3,
            worksiteId: 7,
            departmentId: 8,
            jobRoleId: 9,
            name: 'Packing line lift',
            taskCode: 'PK-01',
        ));
        $assessment = Assessment::create(
            id: 1,
            uuid: '33333333-3333-4333-8333-333333333333',
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
            createdAt: '2026-07-09 08:00:00',
        );
        $settings = new WorkerVoiceSettings(new SettingsService([
            'worker_voice.body_regions' => [
                ['key' => 'neck', 'label' => 'Neck'],
                ['key' => 'lower_back', 'label' => 'Lower Back'],
            ],
            'worker_voice.question_catalog' => [],
            'worker_voice.max_suggested_change_length' => 500,
            'worker_voice.max_trend_sample_size' => 5000,
            'worker_voice.require_task_or_assessment' => true,
        ]));
        $audit = new RecordingWorkerVoiceAudit();
        $viewService = new WorkerFeedbackViewService();
        $actor = new UserContext(
            userId: 44,
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            roleType: 'worker',
            privileges: [
                WorkerVoicePermissions::SUBMIT,
                WorkerVoicePermissions::VIEW,
                WorkerVoicePermissions::VIEW_AGGREGATES,
            ],
        );

        $submit = new SubmitWorkerFeedbackUseCase(
            $repo,
            $tasks,
            new SingleWorkerVoiceAssessmentRepository($assessment),
            new AllowWorkerVoicePermissions(),
            new PassthroughWorkerVoiceTx(),
            $audit,
            $settings,
            $viewService,
            new SingleWorkerVoiceWorksiteRepository(new Worksite(7, '77777777-7777-4777-8777-777777777777', 3, 'Main Plant')),
            new SingleWorkerVoiceDepartmentRepository(new Department(8, '88888888-8888-4888-8888-888888888888', 3, 7, null, 'Assembly')),
            new SingleWorkerVoiceJobRoleRepository(new JobRole(9, '99999999-9999-4999-8999-999999999999', 3, 8, 'Handler')),
        );

        $created = $submit->execute([
            'assessmentUuid' => '33333333-3333-4333-8333-333333333333',
            'bodyRegion' => 'lower_back',
            'anonymousStatus' => true,
            'hasDiscomfort' => true,
            'discomfortLevel' => 4,
            'frequencyLevel' => 3,
            'difficultyLevel' => 2,
            'reportingComfortLevel' => 1,
            'pain7DayLevel' => 4,
            'pain30DayLevel' => 5,
            'suggestedChange' => 'Add lift assist.',
        ], $actor);

        self::assertSame('22222222-2222-4222-8222-222222222222', $created['taskUuid']);
        self::assertSame('88888888-8888-4888-8888-888888888888', $created['departmentUuid']);
        self::assertTrue($created['anonymousStatus']);
        self::assertNull($created['submittedByUserId']);

        $get = new GetWorkerFeedbackUseCase($repo, new AllowWorkerVoicePermissions(), $viewService, $audit);
        $detail = $get->execute($created['uuid'], $actor);
        self::assertNull($detail['submittedByUserId']);

        $list = new ListWorkerFeedbackUseCase($repo, new AllowWorkerVoicePermissions(), $viewService);
        $rows = $list->execute($actor, ['bodyRegion' => 'lower_back']);
        self::assertCount(1, $rows);
        self::assertSame('lower_back', $rows[0]['bodyRegion']);

        $trends = new GetWorkerFeedbackTrendsUseCase(
            $repo,
            new AllowWorkerVoicePermissions(),
            new WorkerFeedbackTrendService($tasks),
            $settings,
            $audit,
        );
        $trendData = $trends->execute($actor);
        self::assertSame(1, $trendData['summary']['totalResponses']);
        self::assertSame(100.0, $trendData['summary']['anonymousRate']);
        self::assertSame('lower_back', $trendData['byBodyRegion'][0]['bodyRegion']);
        self::assertSame('Packing line lift', $trendData['byTask'][0]['taskName']);
        self::assertSame('PK-01', $trendData['byTaskType'][0]['taskType']);
        self::assertSame('88888888-8888-4888-8888-888888888888', $trendData['byDepartment'][0]['departmentUuid']);
        self::assertSame(date('Y-m-d'), $trendData['timeline'][0]['date']);
    }

    public function test_sensitive_permission_can_view_identity_for_non_anonymous_feedback(): void
    {
        $repo = new InMemoryWorkerVoiceRepository([
            new WorkerFeedback(
                id: 1,
                uuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                organizationId: 3,
                organizationUuid: '11111111-1111-4111-8111-111111111111',
                taskId: 5,
                taskUuid: '22222222-2222-4222-8222-222222222222',
                assessmentUuid: null,
                worksiteId: null,
                worksiteUuid: null,
                departmentId: null,
                departmentUuid: null,
                jobRoleId: null,
                jobRoleUuid: null,
                submittedByUserId: 55,
                anonymousStatus: false,
                bodyRegion: 'neck',
                hasDiscomfort: true,
                discomfortLevel: 3,
                frequencyLevel: 2,
                difficultyLevel: 2,
                reportingComfortLevel: 3,
                pain7DayLevel: 3,
                pain30DayLevel: 4,
                suggestedChange: null,
                createdAt: '2026-07-09 09:00:00',
                updatedAt: '2026-07-09 09:00:00',
            ),
        ]);

        $detail = (new GetWorkerFeedbackUseCase($repo, new AllowWorkerVoicePermissions(), new WorkerFeedbackViewService(), new RecordingWorkerVoiceAudit()))->execute(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            new UserContext(
                userId: 44,
                organizationId: 3,
                organizationUuid: '11111111-1111-4111-8111-111111111111',
                roleType: 'safety_manager',
                privileges: [WorkerVoicePermissions::VIEW, WorkerVoicePermissions::VIEW_SENSITIVE],
            ),
        );

        self::assertSame(55, $detail['submittedByUserId']);
    }

    public function test_feedback_is_scoped_to_actor_organization(): void
    {
        $repo = new InMemoryWorkerVoiceRepository([
            new WorkerFeedback(
                id: 1,
                uuid: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                organizationId: 8,
                organizationUuid: '88888888-8888-4888-8888-888888888888',
                taskId: null,
                taskUuid: null,
                assessmentUuid: null,
                worksiteId: null,
                worksiteUuid: null,
                departmentId: null,
                departmentUuid: null,
                jobRoleId: null,
                jobRoleUuid: null,
                submittedByUserId: null,
                anonymousStatus: true,
                bodyRegion: 'neck',
                hasDiscomfort: true,
                discomfortLevel: 2,
                frequencyLevel: 2,
                difficultyLevel: 2,
                reportingComfortLevel: 2,
                pain7DayLevel: 2,
                pain30DayLevel: 2,
                suggestedChange: null,
                createdAt: '2026-07-09 09:00:00',
                updatedAt: '2026-07-09 09:00:00',
            ),
        ]);

        $this->expectException(NotFoundException::class);
        (new GetWorkerFeedbackUseCase($repo, new AllowWorkerVoicePermissions(), new WorkerFeedbackViewService(), new RecordingWorkerVoiceAudit()))->execute(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            new UserContext(userId: 44, organizationId: 3, organizationUuid: '11111111-1111-4111-8111-111111111111', roleType: 'staff', privileges: [WorkerVoicePermissions::VIEW]),
        );
    }
}

final class InMemoryWorkerVoiceRepository implements IWorkerVoiceRepository
{
    /** @var array<string, WorkerFeedback> */
    private array $items = [];
    private int $nextId = 1;

    /** @param list<WorkerFeedback> $items */
    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->items[$item->uuid] = $item;
        }
    }

    public function create(WorkerFeedback $feedback): int
    {
        $id = $feedback->id ?? $this->nextId++;
        $this->items[$feedback->uuid] = new WorkerFeedback(
            id: $id,
            uuid: $feedback->uuid,
            organizationId: $feedback->organizationId,
            organizationUuid: $feedback->organizationUuid,
            taskId: $feedback->taskId,
            taskUuid: $feedback->taskUuid,
            assessmentUuid: $feedback->assessmentUuid,
            worksiteId: $feedback->worksiteId,
            worksiteUuid: $feedback->worksiteUuid,
            departmentId: $feedback->departmentId,
            departmentUuid: $feedback->departmentUuid,
            jobRoleId: $feedback->jobRoleId,
            jobRoleUuid: $feedback->jobRoleUuid,
            submittedByUserId: $feedback->submittedByUserId,
            anonymousStatus: $feedback->anonymousStatus,
            bodyRegion: $feedback->bodyRegion,
            hasDiscomfort: $feedback->hasDiscomfort,
            discomfortLevel: $feedback->discomfortLevel,
            frequencyLevel: $feedback->frequencyLevel,
            difficultyLevel: $feedback->difficultyLevel,
            reportingComfortLevel: $feedback->reportingComfortLevel,
            pain7DayLevel: $feedback->pain7DayLevel,
            pain30DayLevel: $feedback->pain30DayLevel,
            suggestedChange: $feedback->suggestedChange,
            metadata: $feedback->metadata,
            createdAt: $feedback->createdAt,
            updatedAt: $feedback->updatedAt,
        );

        return $id;
    }

    public function findByUuid(string $uuid): ?WorkerFeedback
    {
        return $this->items[$uuid] ?? null;
    }

    public function findAllByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $rows = array_values(array_filter($this->items, static function (WorkerFeedback $item) use ($organizationId, $filters): bool {
            if ($item->organizationId !== $organizationId) {
                return false;
            }
            if (($filters['bodyRegion'] ?? null) && $item->bodyRegion !== $filters['bodyRegion']) {
                return false;
            }
            if (($filters['taskUuid'] ?? null) && $item->taskUuid !== $filters['taskUuid']) {
                return false;
            }
            if (($filters['assessmentUuid'] ?? null) && $item->assessmentUuid !== $filters['assessmentUuid']) {
                return false;
            }
            if (($filters['anonymousStatus'] ?? null) !== null && $filters['anonymousStatus'] !== '') {
                $expected = in_array($filters['anonymousStatus'], [true, 1, '1', 'true'], true);
                if ($item->anonymousStatus !== $expected) {
                    return false;
                }
            }

            return true;
        }));

        return array_slice($rows, $offset, $limit);
    }
}

final class AllowWorkerVoicePermissions implements IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        if (!in_array($privilege, $ctx->privileges, true)) {
            throw new \RuntimeException('Missing expected privilege in test context: ' . $privilege);
        }
    }
}

final class PassthroughWorkerVoiceTx implements TransactionManagerInterface
{
    public function transactional(callable $callback): mixed
    {
        return $callback();
    }
}

final class RecordingWorkerVoiceAudit implements IAuditService
{
    public array $records = [];

    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
        $this->records[] = compact('action', 'entityType', 'entityId', 'beforeState', 'afterState', 'actorId', 'actorType', 'idempotencyKey', 'metadata');
    }
}

final class SingleWorkerVoiceTaskRepository implements ITaskRepository
{
    public function __construct(private readonly Task $task) {}
    public function create(Task $task): int { return (int) $task->getId(); }
    public function update(Task $task): void {}
    public function delete(string $uuid): void {}
    public function findByUuid(string $uuid): ?Task { return $uuid === $this->task->getUuid() ? $this->task : null; }
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array { return $organizationId === $this->task->getOrganizationId() ? [$this->task] : []; }
}

final class SingleWorkerVoiceAssessmentRepository implements IAssessmentRepository
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

final class SingleWorkerVoiceWorksiteRepository implements IWorksiteRepository
{
    public function __construct(private readonly Worksite $worksite) {}
    public function create(Worksite $worksite): int { return (int) $worksite->getId(); }
    public function update(Worksite $worksite): void {}
    public function delete(string $uuid): void {}
    public function findByUuid(string $uuid): ?Worksite { return $uuid === $this->worksite->getUuid() ? $this->worksite : null; }
    public function findById(int $id): ?Worksite { return $id === $this->worksite->getId() ? $this->worksite : null; }
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array { return $organizationId === $this->worksite->getOrganizationId() ? [$this->worksite] : []; }
}

final class SingleWorkerVoiceDepartmentRepository implements IDepartmentRepository
{
    public function __construct(private readonly Department $department) {}
    public function create(Department $department): int { return (int) $department->getId(); }
    public function update(Department $department): void {}
    public function delete(string $uuid): void {}
    public function findByUuid(string $uuid): ?Department { return $uuid === $this->department->getUuid() ? $this->department : null; }
    public function findById(int $id): ?Department { return $id === $this->department->getId() ? $this->department : null; }
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array { return $organizationId === $this->department->getOrganizationId() ? [$this->department] : []; }
}

final class SingleWorkerVoiceJobRoleRepository implements IJobRoleRepository
{
    public function __construct(private readonly JobRole $jobRole) {}
    public function create(JobRole $jobRole): int { return (int) $jobRole->getId(); }
    public function update(JobRole $jobRole): void {}
    public function delete(string $uuid): void {}
    public function findByUuid(string $uuid): ?JobRole { return $uuid === $this->jobRole->getUuid() ? $this->jobRole : null; }
    public function findById(int $id): ?JobRole { return $id === $this->jobRole->getId() ? $this->jobRole : null; }
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array { return $organizationId === $this->jobRole->getOrganizationId() ? [$this->jobRole] : []; }
}
