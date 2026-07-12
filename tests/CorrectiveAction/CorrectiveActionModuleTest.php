<?php

declare(strict_types=1);

namespace WorkEddy\Tests\CorrectiveAction;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\AiScoreOutput;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\CorrectiveAction\Application\AssignCorrectiveActionUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\GenerateRecommendationsUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ListCorrectiveActionLibraryUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ListRecommendationRulesUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ReviewRecommendationUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\RunCorrectiveActionMaintenanceUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\SeedCorrectiveActionDefaultsUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\Services\ControlActionWorkflowService;
use WorkEddy\Modules\CorrectiveAction\Application\Services\ControlRecommendationService;
use WorkEddy\Modules\CorrectiveAction\Application\UpdateCorrectiveActionStatusUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\UpsertCorrectiveActionLibraryItemUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\UpsertRecommendationRuleUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\UploadCorrectiveActionEvidenceUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\VerifyCorrectiveActionUseCase;
use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveAction;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionLibraryItem;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionRecommendation;
use WorkEddy\Modules\CorrectiveAction\Domain\RecommendationRule;
use WorkEddy\Modules\CorrectiveAction\Settings\CorrectiveActionSettings;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Events\InMemoryEventPublisher;
use WorkEddy\Platform\Schema\Modules\CorrectiveAction\CorrectiveActionSchemaBuilder;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Exceptions\ValidationException;

final class CorrectiveActionModuleTest extends TestCase
{
    public function test_generates_reviews_assigns_tracks_evidence_and_verifies_action(): void
    {
        $assessment = $this->reviewedAssessment();
        $assessments = new CorrectiveAssessmentRepository($assessment);
        $repo = new InMemoryCorrectiveActionRepository();
        $audit = new CorrectiveAuditService();
        $permissions = new CorrectivePermissionService();
        $settings = new CorrectiveActionSettings(new SettingsService([
            'corrective_action.default_due_days' => 30,
            'corrective_action.follow_up_days_after_verification' => 14,
            'corrective_action.require_evidence_for_completion' => true,
        ]));
        $actor = new UserContext(userId: 44, organizationId: 3, organizationUuid: '11111111-1111-4111-8111-111111111111', roleType: 'staff', privileges: [
            CorrectiveActionPermissions::GENERATE_RECOMMENDATIONS,
            CorrectiveActionPermissions::REVIEW_RECOMMENDATIONS,
            CorrectiveActionPermissions::ASSIGN,
            CorrectiveActionPermissions::UPDATE_STATUS,
            CorrectiveActionPermissions::UPLOAD_EVIDENCE,
            CorrectiveActionPermissions::VERIFY,
        ]);

        $recommendations = (new GenerateRecommendationsUseCase($assessments, $repo, new ControlRecommendationService(), $permissions, $audit))->execute($assessment->getUuid(), $actor);
        self::assertNotSame([], $recommendations);
        self::assertSame('elimination', $recommendations[0]['hierarchyLevel']);

        $accepted = (new ReviewRecommendationUseCase($repo, $permissions, $audit))->accept($recommendations[0]['uuid'], $actor, ['title' => 'Use lift table']);
        self::assertSame('accepted', $accepted['status']);
        self::assertSame('Use lift table', $accepted['title']);

        $action = (new AssignCorrectiveActionUseCase($repo, $settings, $permissions, $audit))->execute($accepted['uuid'], $actor, 55, '2026-08-01');
        self::assertSame('assigned', $action['status']);
        self::assertSame(55, $action['assignedToUserId']);

        $inProgress = (new UpdateCorrectiveActionStatusUseCase($repo, new ControlActionWorkflowService(), $permissions, $audit))->execute($action['uuid'], 'in_progress', $actor);
        self::assertSame('in_progress', $inProgress['status']);

        $evidence = (new UploadCorrectiveActionEvidenceUseCase($repo, new CorrectiveStorageService(), $permissions, $audit))->execute($action['uuid'], $actor, [
            'name' => 'proof.jpg',
            'tmp_name' => __FILE__,
            'type' => 'image/jpeg',
            'size' => 123,
            'error' => UPLOAD_ERR_OK,
        ], 'photo', 'Installed lift table.');
        self::assertSame('stored-evidence', $evidence['storageFileUuid']);

        $completed = (new UpdateCorrectiveActionStatusUseCase($repo, new ControlActionWorkflowService(), $permissions, $audit))->execute($action['uuid'], 'completed', $actor);
        self::assertSame('completed', $completed['status']);

        $verified = (new VerifyCorrectiveActionUseCase($repo, new ControlActionWorkflowService(), $settings, $permissions, $audit))->execute($action['uuid'], $actor);
        self::assertSame('verified', $verified['status']);
        self::assertNotNull($verified['followUpAssessmentDueDate']);
        self::assertContains('corrective_action.verified', array_column($audit->records, 'action'));
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $repo = new InMemoryCorrectiveActionRepository();
        $action = new CorrectiveAction(id: null, uuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', organizationId: 3, organizationUuid: '11111111-1111-4111-8111-111111111111', assessmentUuid: '44444444-4444-4444-8444-444444444444', recommendationUuid: null, libraryItemUuid: null, title: 'Fix lift', description: null, reason: null, controlType: 'permanent', hierarchyLevel: 'engineering', priority: 'high', status: 'assigned', assignedToUserId: 55, assignedByUserId: 44, dueDate: '2026-08-01');
        $repo->createAction($action);

        $this->expectException(ValidationException::class);
        (new UpdateCorrectiveActionStatusUseCase($repo, new ControlActionWorkflowService(), new CorrectivePermissionService(), new CorrectiveAuditService()))->execute($action->uuid, 'verified', new UserContext(userId: 44, organizationId: 3, roleType: 'staff', privileges: [CorrectiveActionPermissions::UPDATE_STATUS]));
    }

    public function test_manages_corrective_action_library_and_recommendation_rules(): void
    {
        $repo = new InMemoryCorrectiveActionRepository();
        $permissions = new CorrectivePermissionService();
        $audit = new CorrectiveAuditService();
        $actor = new UserContext(userId: 44, organizationId: 3, roleType: 'staff', privileges: [
            CorrectiveActionPermissions::VIEW,
            CorrectiveActionPermissions::MANAGE_LIBRARY,
        ]);

        $item = (new UpsertCorrectiveActionLibraryItemUseCase($repo, $permissions, $audit))->execute($actor, [
            'title' => 'Install adjustable lift table',
            'description' => 'Reduce manual lift force.',
            'reason' => 'Observed lifting force is too high for the current station.',
            'controlType' => 'lift_assist',
            'hierarchyLevel' => 'engineering',
            'riskFactor' => 'manual_handling',
            'taskType' => 'lifting',
            'priority' => 'high',
            'dueDays' => 21,
            'followUpDays' => 14,
            'evidenceTypes' => ['photo', 'worker_feedback'],
            'isActive' => true,
        ]);
        self::assertSame('Install adjustable lift table', $item['title']);
        self::assertSame('engineering', $item['hierarchyLevel']);
        self::assertTrue($item['isActive']);
        self::assertSame('Observed lifting force is too high for the current station.', $item['reason']);
        self::assertSame(['photo', 'worker_feedback'], $item['evidenceTypes']);

        $rule = (new UpsertRecommendationRuleUseCase($repo, $permissions, $audit))->execute($actor, [
            'condition' => [
                'assessmentType' => 'reba',
                'riskFactor' => 'manual_handling',
                'minScore' => 70,
                'confidenceThreshold' => 0.7,
            ],
            'action' => ['libraryItemUuid' => $item['uuid']],
            'weight' => 200,
            'isActive' => true,
        ]);
        self::assertSame(200, $rule['weight']);
        self::assertTrue($rule['isActive']);

        $library = (new ListCorrectiveActionLibraryUseCase($repo, $permissions))->execute($actor, [
            'search' => 'lift',
            'risk_factor' => 'manual_handling',
            'status' => 'active',
        ]);
        self::assertSame(1, $library['meta']['total']);
        self::assertSame(1, $library['summary']['totalActions']);
        self::assertSame(1, $library['summary']['activeActions']);
        self::assertSame('active', $library['items'][0]['status']);
        self::assertSame(1, $library['items'][0]['linkedRuleCount']);
        self::assertSame('manual_handling', $library['items'][0]['bodyArea']);

        $rules = (new ListRecommendationRulesUseCase($repo, $permissions))->execute($actor, [
            'search' => 'lift',
            'status' => 'active',
            'assessment_type' => 'reba',
            'review_needed' => '0',
        ]);
        self::assertSame(1, $rules['meta']['total']);
        self::assertSame(1, $rules['summary']['activeRules']);
        self::assertSame(0, $rules['summary']['rulesNeedingReview']);
        self::assertSame($item['uuid'], $rules['items'][0]['linkedActionId']);
        self::assertSame('Install adjustable lift table', $rules['items'][0]['linkedActionTitle']);
        self::assertFalse($rules['items'][0]['needsReview']);
        self::assertSame('active', $rules['items'][0]['status']);
    }

    public function test_generation_matches_active_rules_to_library_actions(): void
    {
        $assessment = $this->reviewedAssessment();
        $assessments = new CorrectiveAssessmentRepository($assessment);
        $repo = new InMemoryCorrectiveActionRepository();
        $permissions = new CorrectivePermissionService();
        $audit = new CorrectiveAuditService();
        $actor = new UserContext(userId: 44, organizationId: 3, roleType: 'staff', privileges: [
            CorrectiveActionPermissions::GENERATE_RECOMMENDATIONS,
            CorrectiveActionPermissions::MANAGE_LIBRARY,
        ]);

        $item = (new UpsertCorrectiveActionLibraryItemUseCase($repo, $permissions, $audit))->execute($actor, [
            'title' => 'Remove overhead reach from station',
            'description' => 'Move work into neutral reach zone.',
            'reason' => 'Shoulder elevation remains outside the neutral reach zone.',
            'controlType' => 'workstation_redesign',
            'hierarchyLevel' => 'elimination',
            'riskFactor' => 'awkward_posture',
            'taskType' => 'reaching',
            'priority' => 'high',
            'dueDays' => 18,
            'followUpDays' => 10,
            'evidenceTypes' => ['photo'],
            'isActive' => true,
        ]);
        (new UpsertRecommendationRuleUseCase($repo, $permissions, $audit))->execute($actor, [
            'condition' => [
                'assessmentType' => 'reba',
                'riskFactor' => 'awkward_posture',
                'minScore' => 70,
            ],
            'action' => ['libraryItemUuid' => $item['uuid']],
            'weight' => 500,
            'isActive' => true,
        ]);

        $recommendations = (new GenerateRecommendationsUseCase($assessments, $repo, new ControlRecommendationService(), $permissions, $audit))->execute($assessment->getUuid(), $actor);

        self::assertSame($item['uuid'], $recommendations[0]['libraryItemUuid']);
        self::assertSame('Remove overhead reach from station', $recommendations[0]['title']);
        self::assertSame('corrective_action_library', $recommendations[0]['evidence']['source']);
        self::assertSame(18, $recommendations[0]['dueDays']);
        self::assertSame(10, $recommendations[0]['followUpDays']);
    }

    public function test_marks_rules_needing_review_when_linked_action_is_inactive(): void
    {
        $repo = new InMemoryCorrectiveActionRepository();
        $permissions = new CorrectivePermissionService();
        $audit = new CorrectiveAuditService();
        $actor = new UserContext(userId: 44, organizationId: 3, roleType: 'staff', privileges: [
            CorrectiveActionPermissions::VIEW,
            CorrectiveActionPermissions::MANAGE_LIBRARY,
        ]);

        $item = (new UpsertCorrectiveActionLibraryItemUseCase($repo, $permissions, $audit))->execute($actor, [
            'title' => 'Raise pallet height',
            'controlType' => 'lift_assist',
            'hierarchyLevel' => 'engineering',
            'priority' => 'medium',
            'isActive' => false,
        ]);
        self::assertFalse($item['isActive']);

        (new UpsertRecommendationRuleUseCase($repo, $permissions, $audit))->execute($actor, [
            'condition' => [
                'assessmentType' => 'rula',
                'minScore' => 60,
            ],
            'action' => ['libraryItemUuid' => $item['uuid']],
            'weight' => 100,
            'isActive' => true,
        ]);

        $rules = (new ListRecommendationRulesUseCase($repo, $permissions))->execute($actor, ['review_needed' => '1']);

        self::assertCount(1, $rules['items']);
        self::assertTrue($rules['items'][0]['needsReview']);
        self::assertSame('inactive_linked_action', $rules['items'][0]['reviewReason']);
    }

    public function test_seeds_defaults_marks_overdue_and_emits_follow_up_events(): void
    {
        $repo = new InMemoryCorrectiveActionRepository();
        $seeded = (new SeedCorrectiveActionDefaultsUseCase($repo))->execute();
        self::assertSame(5, $seeded['library_items']);
        self::assertSame(5, $seeded['recommendation_rules']);

        $repo->createAction(new CorrectiveAction(id: null, uuid: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', organizationId: 3, organizationUuid: '11111111-1111-4111-8111-111111111111', assessmentUuid: '44444444-4444-4444-8444-444444444444', recommendationUuid: null, libraryItemUuid: null, title: 'Late action', description: null, reason: null, controlType: 'permanent', hierarchyLevel: 'engineering', priority: 'high', status: 'assigned', assignedToUserId: 55, assignedByUserId: 44, dueDate: '2026-01-01'));
        $repo->createOrUpdateFollowUp('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', '2026-01-15');

        $events = new InMemoryEventPublisher();
        $result = (new RunCorrectiveActionMaintenanceUseCase($repo, $events))->execute('2026-02-01');

        self::assertSame(1, $result['overdue_actions']);
        self::assertSame(1, $result['follow_ups_due']);
        self::assertSame('overdue', $repo->actions['bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb']->status);
        self::assertSame('due', $repo->followUps['bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb']['status']);
        self::assertCount(2, $events->published());
    }

    public function test_schema_contains_phase_four_tables(): void
    {
        $tables = (new CorrectiveActionSchemaBuilder())->tables();
        self::assertContains('corrective_action_library', $tables);
        self::assertContains('corrective_actions', $tables);
        self::assertContains('corrective_action_evidence', $tables);
        self::assertContains('corrective_action_follow_ups', $tables);
    }

    private function reviewedAssessment(): Assessment
    {
        return Assessment::create(
            id: 1,
            uuid: '44444444-4444-4444-8444-444444444444',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            taskId: 5,
            taskUuid: '22222222-2222-4222-8222-222222222222',
            model: 'reba',
            metrics: ['trunk_angle' => 70, 'upper_arm_angle' => 100, 'repetition_count' => 30, 'load_weight' => 20],
            initialScore: ['raw_score' => 12, 'normalized_score' => 80, 'risk_category' => 'high'],
            riskFactors: ['awkward_posture'],
            bodyRegions: [],
            createdBy: 44,
        )->markSubmitted()->markReviewed(44, 'Reviewer', 'CPE', null, ['raw_score' => 12, 'normalized_score' => 80, 'risk_category' => 'high'], null, true);
    }
}

final class CorrectiveAssessmentRepository implements IAssessmentRepository
{
    public function __construct(private Assessment $assessment) {}
    public function create(Assessment $assessment): int { return 1; }
    public function update(Assessment $assessment): void { $this->assessment = $assessment; }
    public function addVideo(AssessmentVideo $video): int { return 1; }
    public function updateVideoProcessing(AssessmentVideo $video): void {}
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

final class InMemoryCorrectiveActionRepository implements ICorrectiveActionRepository
{
    /** @var array<string, CorrectiveActionRecommendation> */
    public array $recommendations = [];
    /** @var array<string, CorrectiveAction> */
    public array $actions = [];
    /** @var array<string, CorrectiveActionLibraryItem> */
    public array $library = [];
    /** @var array<string, RecommendationRule> */
    public array $rules = [];
    public array $evidence = [];
    public array $history = [];
    public array $followUps = [];

    public function replaceRecommendationsForAssessment(string $assessmentUuid, array $recommendations): void { foreach ($recommendations as $r) { $this->recommendations[$r->uuid] = $r; } }
    public function listRecommendationsByAssessment(string $assessmentUuid): array { return array_values(array_filter($this->recommendations, fn($r) => $r->assessmentUuid === $assessmentUuid)); }
    public function findRecommendationByUuid(string $uuid): ?CorrectiveActionRecommendation { return $this->recommendations[$uuid] ?? null; }
    public function updateRecommendation(CorrectiveActionRecommendation $recommendation): void { $this->recommendations[$recommendation->uuid] = $recommendation; }
    public function createAction(CorrectiveAction $action): int { $this->actions[$action->uuid] = $action; return count($this->actions); }
    public function updateAction(CorrectiveAction $action): void { $this->actions[$action->uuid] = $action; }
    public function findActionByUuid(string $uuid): ?CorrectiveAction { return $this->actions[$uuid] ?? null; }
    public function listActionsByOrganizationId(int $organizationId, array $filters = []): array { return array_values($this->actions); }
    public function addEvidence(array $data): array { $this->evidence[] = $data; return $data; }
    public function listEvidenceByActionUuid(string $actionUuid): array { return array_values(array_filter($this->evidence, static fn(array $row): bool => ($row['actionUuid'] ?? $row['action_uuid'] ?? null) === $actionUuid)); }
    public function addStatusHistory(array $data): void { $this->history[] = $data; }
    public function listStatusHistoryByActionUuid(string $actionUuid): array { return array_values(array_filter($this->history, static fn(array $row): bool => ($row['actionUuid'] ?? $row['action_uuid'] ?? null) === $actionUuid)); }
    public function upsertLibraryItem(CorrectiveActionLibraryItem $item): CorrectiveActionLibraryItem { $this->library[$item->uuid] = $item; return $item; }
    public function findLibraryItemByUuid(string $uuid): ?CorrectiveActionLibraryItem { return $this->library[$uuid] ?? null; }
    public function countRulesForLibraryItem(string $libraryItemUuid): int
    {
        return count(array_filter($this->rules, static fn(RecommendationRule $rule): bool => ($rule->action['libraryItemUuid'] ?? null) === $libraryItemUuid));
    }
    public function listLibraryItems(array $filters = []): array
    {
        return array_values(array_filter($this->library, static function (CorrectiveActionLibraryItem $item) use ($filters): bool {
            $search = strtolower(trim((string) ($filters['search'] ?? '')));
            if ($search !== '') {
                $haystack = strtolower(implode(' ', [$item->title, $item->description ?? '', $item->riskFactor ?? '', $item->taskType ?? '']));
                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }
            if (($filters['risk_factor'] ?? null) !== null && $item->riskFactor !== $filters['risk_factor']) {
                return false;
            }
            if (($filters['task_type'] ?? null) !== null && $item->taskType !== $filters['task_type']) {
                return false;
            }
            if (($filters['industry'] ?? null) !== null && $item->industry !== $filters['industry']) {
                return false;
            }
            if (($filters['category'] ?? null) !== null && $filters['category'] !== '' && $item->hierarchyLevel !== $filters['category']) {
                return false;
            }
            if (($filters['risk_level'] ?? null) !== null && $filters['risk_level'] !== '' && $item->priority !== $filters['risk_level']) {
                return false;
            }
            if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
                $expectedActive = $filters['status'] === 'active';
                if ($item->isActive !== $expectedActive) {
                    return false;
                }
            }
            return true;
        }));
    }
    public function upsertRecommendationRule(RecommendationRule $rule): RecommendationRule { $this->rules[$rule->uuid] = $rule; return $rule; }
    public function listRecommendationRules(array $filters = []): array
    {
        return array_values(array_filter($this->rules, function (RecommendationRule $rule) use ($filters): bool {
            if (($filters['status'] ?? '') !== '') {
                $expectedActive = $filters['status'] === 'active';
                if ($rule->isActive !== $expectedActive) {
                    return false;
                }
            }
            if (($filters['assessment_type'] ?? '') !== '' && ($rule->condition['assessmentType'] ?? null) !== $filters['assessment_type']) {
                return false;
            }
            if (($filters['linked_action'] ?? '') !== '' && ($rule->action['libraryItemUuid'] ?? null) !== $filters['linked_action']) {
                return false;
            }

            $linkedItem = isset($rule->action['libraryItemUuid']) ? ($this->library[(string) $rule->action['libraryItemUuid']] ?? null) : null;
            $needsReview = !isset($rule->action['libraryItemUuid']) || $linkedItem === null || !$linkedItem->isActive;
            if (($filters['review_needed'] ?? '') !== '') {
                if ($needsReview !== ($filters['review_needed'] === '1')) {
                    return false;
                }
            }

            $search = strtolower(trim((string) ($filters['search'] ?? '')));
            if ($search !== '') {
                $haystack = strtolower(implode(' ', [
                    $linkedItem?->title ?? '',
                    $rule->uuid,
                    (string) ($rule->condition['riskFactor'] ?? ''),
                    (string) ($rule->condition['assessmentType'] ?? ''),
                ]));
                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }

            return true;
        }));
    }
    public function listDueActions(string $beforeDate, int $limit = 100): array
    {
        return array_slice(array_values(array_filter($this->actions, static fn(CorrectiveAction $action): bool => $action->dueDate !== null && $action->dueDate < $beforeDate && in_array($action->status, ['assigned', 'in_progress'], true))), 0, $limit);
    }
    public function listDueFollowUps(string $beforeDate, int $limit = 100): array
    {
        return array_slice(array_values(array_filter($this->followUps, static fn(array $followUp): bool => $followUp['due_date'] <= $beforeDate && $followUp['status'] === 'scheduled')), 0, $limit);
    }
    public function createOrUpdateFollowUp(string $actionUuid, string $dueDate, ?string $followUpAssessmentUuid = null, string $status = 'scheduled'): void
    {
        $this->followUps[$actionUuid] = ['action_uuid' => $actionUuid, 'due_date' => $dueDate, 'follow_up_assessment_uuid' => $followUpAssessmentUuid, 'status' => $status];
    }
    public function updateFollowUpStatus(string $actionUuid, string $status): void
    {
        if (isset($this->followUps[$actionUuid])) {
            $this->followUps[$actionUuid]['status'] = $status;
        }
    }
}

final class CorrectivePermissionService implements IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        if (!in_array($privilege, $ctx->privileges, true)) {
            throw new \RuntimeException('Missing privilege ' . $privilege);
        }
    }
}

final class CorrectiveAuditService implements IAuditService
{
    public array $records = [];
    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void { $this->records[] = compact('action', 'entityType', 'entityId', 'beforeState', 'afterState', 'actorId', 'actorType', 'idempotencyKey', 'metadata'); }
}

final class CorrectiveStorageService implements IStorageService
{
    public function storeUploadedFile(StoreUploadedFileRequest $request): ?StoredFileDTO { return new StoredFileDTO(null, 'stored-evidence', 'local', 'private', 'active', 'corrective-action/evidence.jpg', $request->ownerType, $request->ownerUuid, $request->fieldName, (string) $request->file['name'], (string) $request->file['type'], 'jpg', (int) $request->file['size']); }
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
