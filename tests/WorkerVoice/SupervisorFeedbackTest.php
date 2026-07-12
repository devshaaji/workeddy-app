<?php

declare(strict_types=1);

namespace WorkEddy\Tests\WorkerVoice;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\AiScoreOutput;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;
use WorkEddy\Modules\Assessment\Domain\ComparisonReport;
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
use WorkEddy\Modules\WorkerVoice\Application\GetSupervisorFeedbackTrendsUseCase;
use WorkEddy\Modules\WorkerVoice\Application\Services\SupervisorFeedbackTrendService;
use WorkEddy\Modules\WorkerVoice\Application\SubmitSupervisorFeedbackUseCase;
use WorkEddy\Modules\WorkerVoice\Authorization\WorkerVoicePermissions;
use WorkEddy\Modules\WorkerVoice\Domain\Contracts\ISupervisorFeedbackRepository;
use WorkEddy\Modules\WorkerVoice\Domain\SupervisorFeedback;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;

final class SupervisorFeedbackTest extends TestCase
{
    public function test_submit_and_summarize_supervisor_feedback(): void
    {
        $task = new Task(
            id: 5,
            uuid: '22222222-2222-4222-8222-222222222222',
            organizationId: 3,
            worksiteId: 7,
            departmentId: 8,
            jobRoleId: 9,
            name: 'Packing line lift',
            taskCode: 'PK-01',
        );
        $assessment = Assessment::create(
            id: 1,
            uuid: '33333333-3333-4333-8333-333333333333',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: $task->getUuid(),
            model: 'reba',
            metrics: [],
            initialScore: ['raw_score' => 8.0],
            riskFactors: [],
            bodyRegions: [],
            createdBy: 44,
            createdAt: '2026-07-09 08:00:00',
        );

        $repo = new InMemorySupervisorFeedbackRepository();
        $submit = new SubmitSupervisorFeedbackUseCase(
            $repo,
            new SingleSupervisorTaskRepository($task),
            new SingleSupervisorAssessmentRepository($assessment),
            new AllowSupervisorFeedbackPermissions(),
            new PassthroughSupervisorTx(),
            new RecordingSupervisorAudit(),
            new SingleSupervisorWorksiteRepository(new Worksite(7, '77777777-7777-4777-8777-777777777777', 3, 'Main Plant')),
            new SingleSupervisorDepartmentRepository(new Department(8, '88888888-8888-4888-8888-888888888888', 3, 7, null, 'Assembly')),
            new SingleSupervisorJobRoleRepository(new JobRole(9, '99999999-9999-4999-8999-999999999999', 3, 8, 'Handler')),
        );

        $actor = new UserContext(
            userId: 44,
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            roleType: 'supervisor',
            privileges: [WorkerVoicePermissions::SUBMIT, WorkerVoicePermissions::VIEW_AGGREGATES],
        );

        $created = $submit->execute([
            'assessmentUuid' => $assessment->getUuid(),
            'bodyRegion' => 'lower_back',
            'observedRiskLevel' => 'High',
            'observedIssueType' => 'Awkward posture',
            'frequencyLevel' => 4,
            'severityLevel' => 5,
            'suggestedChange' => 'Add lift assist.',
            'notes' => 'Observed during peak shift.',
        ], $actor);

        self::assertSame('High', $created['observedRiskLevel']);
        self::assertSame('88888888-8888-4888-8888-888888888888', $created['departmentUuid']);

        $trends = (new GetSupervisorFeedbackTrendsUseCase(
            $repo,
            new AllowSupervisorFeedbackPermissions(),
            new SupervisorFeedbackTrendService(),
            new RecordingSupervisorAudit(),
        ))->execute($actor);

        self::assertSame(1, $trends['summary']['totalResponses']);
        self::assertSame(5.0, $trends['summary']['averageSeverity']);
        self::assertSame('88888888-8888-4888-8888-888888888888', $trends['byDepartment'][0]['departmentUuid']);
        self::assertSame(date('Y-m-d'), $trends['timeline'][0]['date']);
    }
}

final class InMemorySupervisorFeedbackRepository implements ISupervisorFeedbackRepository
{
    /** @var array<string, SupervisorFeedback> */
    private array $items = [];
    private int $nextId = 1;

    public function create(SupervisorFeedback $feedback): int
    {
        $id = $feedback->id ?? $this->nextId++;
        $this->items[$feedback->uuid] = new SupervisorFeedback(
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
            bodyRegion: $feedback->bodyRegion,
            observedRiskLevel: $feedback->observedRiskLevel,
            observedIssueType: $feedback->observedIssueType,
            frequencyLevel: $feedback->frequencyLevel,
            severityLevel: $feedback->severityLevel,
            suggestedChange: $feedback->suggestedChange,
            notes: $feedback->notes,
            createdAt: $feedback->createdAt,
            updatedAt: $feedback->updatedAt,
        );

        return $id;
    }

    public function findByUuid(string $uuid): ?SupervisorFeedback
    {
        return $this->items[$uuid] ?? null;
    }

    public function findAllByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $items = array_values(array_filter($this->items, static function (SupervisorFeedback $feedback) use ($organizationId, $filters): bool {
            if ($feedback->organizationId !== $organizationId) {
                return false;
            }
            if (($filters['bodyRegion'] ?? null) && $feedback->bodyRegion !== $filters['bodyRegion']) {
                return false;
            }

            return true;
        }));

        return array_slice($items, $offset, $limit);
    }
}

final class SingleSupervisorTaskRepository implements ITaskRepository
{
    public function __construct(private readonly Task $task) {}
    public function create(Task $task): int { return (int) $task->getId(); }
    public function update(Task $task): void {}
    public function delete(string $uuid): void {}
    public function findByUuid(string $uuid): ?Task { return $uuid === $this->task->getUuid() ? $this->task : null; }
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array { return $organizationId === $this->task->getOrganizationId() ? [$this->task] : []; }
}

final class SingleSupervisorAssessmentRepository implements IAssessmentRepository
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
    public function createComparisonReport(ComparisonReport $report): int { return 1; }
    public function updateComparisonReport(ComparisonReport $report): void {}
    public function findComparisonReportByUuid(string $uuid): ?ComparisonReport { return null; }
    public function findComparisonReportByBaselineAndFollowUp(string $baselineAssessmentUuid, string $followUpAssessmentUuid): ?ComparisonReport { return null; }
    public function findComparisonReportsByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array { return []; }
}

final class SingleSupervisorWorksiteRepository implements IWorksiteRepository
{
    public function __construct(private readonly Worksite $worksite) {}
    public function create(Worksite $worksite): int { return (int) $worksite->getId(); }
    public function update(Worksite $worksite): void {}
    public function delete(string $uuid): void {}
    public function findByUuid(string $uuid): ?Worksite { return $uuid === $this->worksite->getUuid() ? $this->worksite : null; }
    public function findById(int $id): ?Worksite { return $id === $this->worksite->getId() ? $this->worksite : null; }
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array { return $organizationId === $this->worksite->getOrganizationId() ? [$this->worksite] : []; }
}

final class SingleSupervisorDepartmentRepository implements IDepartmentRepository
{
    public function __construct(private readonly Department $department) {}
    public function create(Department $department): int { return (int) $department->getId(); }
    public function update(Department $department): void {}
    public function delete(string $uuid): void {}
    public function findByUuid(string $uuid): ?Department { return $uuid === $this->department->getUuid() ? $this->department : null; }
    public function findById(int $id): ?Department { return $id === $this->department->getId() ? $this->department : null; }
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array { return $organizationId === $this->department->getOrganizationId() ? [$this->department] : []; }
}

final class SingleSupervisorJobRoleRepository implements IJobRoleRepository
{
    public function __construct(private readonly JobRole $jobRole) {}
    public function create(JobRole $jobRole): int { return (int) $jobRole->getId(); }
    public function update(JobRole $jobRole): void {}
    public function delete(string $uuid): void {}
    public function findByUuid(string $uuid): ?JobRole { return $uuid === $this->jobRole->getUuid() ? $this->jobRole : null; }
    public function findById(int $id): ?JobRole { return $id === $this->jobRole->getId() ? $this->jobRole : null; }
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array { return $organizationId === $this->jobRole->getOrganizationId() ? [$this->jobRole] : []; }
}

final class AllowSupervisorFeedbackPermissions implements IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        if (!in_array($privilege, $ctx->privileges, true)) {
            throw new \RuntimeException('Missing expected privilege in test context: ' . $privilege);
        }
    }
}

final class PassthroughSupervisorTx implements TransactionManagerInterface
{
    public function transactional(callable $callback): mixed
    {
        return $callback();
    }
}

final class RecordingSupervisorAudit implements IAuditService
{
    public array $records = [];

    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
        $this->records[] = compact('action', 'entityType', 'entityId', 'beforeState', 'afterState', 'actorId', 'actorType', 'idempotencyKey', 'metadata');
    }
}
