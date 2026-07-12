<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Application\Services;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Assessment\Application\Services\ValidationAgreementService;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Assessment\Domain\ValidationReview;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\Reporting\Settings\ReportingSettings;
use WorkEddy\Platform\Cache\ICacheService;

final class ReportingSnapshotService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ?ICacheService $cache = null,
        private readonly ?IAssessmentRepository $assessments = null,
        private readonly ?ICorrectiveActionRepository $correctiveActions = null,
        private readonly ?ReportingSettings $settings = null,
    ) {}

    public function dashboard(): array
    {
        if ($this->cache === null) {
            return $this->buildDashboard();
        }

        return $this->cache->get('reporting:dashboard', function (): array {
            return $this->buildDashboard();
        }, 3600);
    }

    private function buildDashboard(): array
    {
        return [
            'customer_summary' => [
                'total_customers' => $this->countTable('customers'),
                'active_customers' => $this->countWhere('customers', "status = 'active'"),
            ],
            'finance_summary' => [
                'income_total' => $this->sumWhere('finance_income_records', 'amount'),
                'expense_total' => $this->sumWhere('finance_expense_records', 'amount'),
                'payroll_gross_total' => $this->sumWhere('finance_payroll_summaries', 'gross_amount'),
            ],

            'staff_summary' => [
                'active_employees' => $this->countWhere('hrm_employees', "status = 'active' AND deleted_at IS NULL"),
            ],
        ];
    }

    public function finance(): array
    {
        if ($this->cache === null) {
            return $this->buildFinance();
        }

        return $this->cache->get('reporting:finance', function (): array {
            return $this->buildFinance();
        }, 3600);
    }

    private function buildFinance(): array
    {
        return [
            'finance_summary' => $this->dashboard()['finance_summary'],
            'income_by_category' => $this->groupTotals('finance_income_records', 'amount', 'category'),
            'expense_by_category' => $this->groupTotals('finance_expense_records', 'amount', 'category'),
            'payroll_periods' => $this->tableRows('finance_payroll_summaries', 'period_key DESC'),
        ];
    }

    public function operations(): array
    {
        if ($this->cache === null) {
            return $this->buildOperations();
        }

        return $this->cache->get('reporting:operations', function (): array {
            return $this->buildOperations();
        }, 3600);
    }

    private function buildOperations(): array
    {
        return [
            'staff_summary' => $this->dashboard()['staff_summary'],
            'customer_summary' => $this->dashboard()['customer_summary'],
        ];
    }

    private function tableExists(string $table): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([$table]);
    }

    private function countTable(string $table): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }

    private function countWhere(string $table, string $where): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where);
    }

    private function sumWhere(string $table, string $column): float
    {
        if (!$this->tableExists($table)) {
            return 0.0;
        }

        return (float) $this->connection->fetchOne('SELECT COALESCE(SUM(' . $column . '), 0) FROM ' . $table);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groupTotals(string $table, string $amountColumn, string $groupColumn): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        return $this->connection->fetchAllAssociative(
            'SELECT ' . $groupColumn . ', COALESCE(SUM(' . $amountColumn . '), 0) AS total FROM ' . $table . ' GROUP BY ' . $groupColumn . ' ORDER BY ' . $groupColumn . ' ASC',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tableRows(string $table, string $orderBy): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        return $this->connection->fetchAllAssociative(
            'SELECT period_key, gross_amount, net_amount, employee_count FROM ' . $table . ' ORDER BY ' . $orderBy,
        );
    }

    public function assessmentReport(string $uuid): array
    {
        if ($this->assessments !== null) {
            $assessment = $this->assessments->findByUuid($uuid);
            if ($assessment !== null) {
                $view = $assessment->toView();
                $aiOutput = $this->assessments->findLatestAiScoreOutput($uuid);
                if ($aiOutput !== null) {
                    $view['aiAssistance'] = [
                        'available' => true,
                        'advisoryOnly' => true,
                        'scoreSource' => $aiOutput->scoreSource,
                        'modelVersion' => $aiOutput->modelVersion,
                        'confidence' => $aiOutput->confidence,
                        'score' => [
                            'raw' => isset($aiOutput->score['raw_score']) ? (float) $aiOutput->score['raw_score'] : (float) ($aiOutput->score['score'] ?? 0),
                            'riskLevel' => $aiOutput->score['risk_level'] ?? null,
                        ],
                        'flags' => $aiOutput->flags,
                    ];
                }

                return $this->buildAssessmentReport($view);
            }
        }

        return [
            'uuid' => $uuid,
            'organizationUuid' => 'workeddy-demo-org',
            'organization' => 'WorkEddy Manufacturing Ltd',
            'worksite' => 'Chicago Assembly Plant',
            'task' => 'Heavy Lifting & Packing - Line A',
            'date' => '2026-07-01',
            'assessor' => 'Sarah Jenkins (Safety Specialist)',
            'reviewer' => 'John Doe (Certified Professional Ergonomist)',
            'risk_score' => 8,
            'risk_level' => 'High Risk',
            'assessment_status' => 'reviewed',
            'score_source' => 'reviewer_confirmed',
            'task_uuid' => null,
            'thumbnail_storage_file_uuid' => null,
            'pose_video_storage_file_uuid' => null,
            'blurred_video_storage_file_uuid' => null,
            'reviewer_notes' => null,
            'adjustment_reason' => null,
            'body_region_heatmap' => [],
            'body_region_scores' => [
                'neck' => 3,
                'back' => 5,
                'shoulders' => 4,
                'wrists' => 2,
                'legs' => 3,
            ],
            'risk_factors' => [
                'Awkward posture during lifting',
                'Repetitive trunk bending (>4 times/min)',
                'Excessive load weight (>15kg without assist)',
            ],
            'recommendations' => [
                'Introduce height-adjustable lift table',
                'Limit continuous sorting duration to 2 hours max per shift',
                'Provide ergonomic wrist support wraps',
            ],
        ];
    }

    public function correctiveActionReport(string $uuid): array
    {
        if ($this->correctiveActions !== null) {
            $action = $this->correctiveActions->findActionByUuid($uuid);
            if ($action !== null) {
                return $this->buildCorrectiveActionReport(
                    $action->toView(),
                    $this->correctiveActions->listEvidenceByActionUuid($uuid),
                    $this->correctiveActions->listStatusHistoryByActionUuid($uuid),
                );
            }
        }

        return [
            'uuid' => $uuid,
            'organizationUuid' => 'workeddy-demo-org',
            'organization' => 'WorkEddy Manufacturing Ltd',
            'actions' => [
                [
                    'id' => 1,
                    'title' => 'Install lift table',
                    'status' => 'Completed',
                    'assignee' => 'Marcus Vance',
                    'due_date' => '2026-07-15',
                    'evidence' => 'Receipt and installation photo uploaded (Ref: STG-9823)',
                ],
                [
                    'id' => 2,
                    'title' => 'Shift rotation policy update',
                    'status' => 'In Progress',
                    'assignee' => 'Sarah Jenkins',
                    'due_date' => '2026-07-20',
                    'evidence' => 'Draft policy in review',
                ],
            ],
        ];
    }

    public function comparisonReport(string $uuid): array
    {
        if ($this->assessments !== null) {
            $report = $this->assessments->findComparisonReportByUuid($uuid);
            if ($report !== null) {
                $baseline = $this->assessments->findByUuid($report->baselineAssessmentUuid);
                $followUp = $this->assessments->findByUuid($report->followUpAssessmentUuid);

                if ($baseline !== null && $followUp !== null) {
                    return $this->buildComparisonReport($report->toView(), $baseline->toView(), $followUp->toView());
                }
            }
        }

        return [
            'uuid' => $uuid,
            'organization' => 'WorkEddy Manufacturing Ltd',
            'baseline' => [
                'date' => '2026-06-01',
                'score' => 8,
                'level' => 'High Risk',
                'scores' => ['neck' => 3, 'back' => 5, 'shoulders' => 4],
                'reviewer_notes' => 'Baseline captured before corrective action.',
                'screenshot_storage_file_uuid' => null,
            ],
            'follow_up' => [
                'date' => '2026-07-01',
                'score' => 3,
                'level' => 'Low Risk',
                'scores' => ['neck' => 1, 'back' => 2, 'shoulders' => 1],
                'reviewer_notes' => 'Follow-up demonstrates measurable improvement.',
                'screenshot_storage_file_uuid' => null,
            ],
            'risk_reduction_pct' => 62.5,
            'original_task_score' => 8,
            'corrected_task_score' => 3,
            'risk_level_before' => 'High Risk',
            'risk_level_after' => 'Low Risk',
            'completed_actions' => [
                'Installed height-adjustable lift table',
                'Updated shift rotation policy',
            ],
            'corrective_action_summary' => [
                'title' => 'Install lift table',
                'status' => 'verified',
                'completedAt' => '2026-06-18 09:30:00',
                'verifiedAt' => '2026-06-19 10:30:00',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $followUp
     * @return array<string, mixed>
     */
    private function buildComparisonReport(array $report, array $baseline, array $followUp): array
    {
        $baselineScore = is_array($report['baselineScore'] ?? null) ? $report['baselineScore'] : [];
        $followUpScore = is_array($report['followUpScore'] ?? null) ? $report['followUpScore'] : [];
        $baselineReview = is_array($baseline['review'] ?? null) ? $baseline['review'] : [];
        $followUpReview = is_array($followUp['review'] ?? null) ? $followUp['review'] : [];
        $completedActions = $this->completedActions($report);
        $correctiveAction = $this->correctiveActionSummary($report);

        return [
            'uuid' => (string) ($report['uuid'] ?? ''),
            'organization' => (string) ($baseline['organizationUuid'] ?? ''),
            'baseline' => [
                'uuid' => (string) ($baseline['uuid'] ?? ''),
                'date' => (string) ($baseline['createdAt'] ?? ''),
                'score' => $baselineScore['raw'] ?? null,
                'level' => $baselineScore['riskLevel'] ?? null,
                'scores' => $this->bodyRegionScoreMap($baseline['bodyRegions'] ?? []),
                'heatmap' => $baseline['bodyRegionHeatmap'] ?? [],
                'reviewer' => $baselineReview['reviewerName'] ?? null,
                'reviewer_notes' => $baselineReview['reviewerNotes'] ?? null,
                'adjustment_reason' => $baselineReview['adjustmentReason'] ?? null,
                'screenshot_storage_file_uuid' => $this->primaryThumbnailUuid($baseline),
            ],
            'follow_up' => [
                'uuid' => (string) ($followUp['uuid'] ?? ''),
                'date' => (string) ($followUp['createdAt'] ?? ''),
                'score' => $followUpScore['raw'] ?? null,
                'level' => $followUpScore['riskLevel'] ?? null,
                'scores' => $this->bodyRegionScoreMap($followUp['bodyRegions'] ?? []),
                'heatmap' => $followUp['bodyRegionHeatmap'] ?? [],
                'reviewer' => $followUpReview['reviewerName'] ?? null,
                'reviewer_notes' => $followUpReview['reviewerNotes'] ?? null,
                'adjustment_reason' => $followUpReview['adjustmentReason'] ?? null,
                'screenshot_storage_file_uuid' => $this->primaryThumbnailUuid($followUp),
            ],
            'original_task_score' => $baselineScore['raw'] ?? null,
            'corrected_task_score' => $followUpScore['raw'] ?? null,
            'risk_level_before' => $baselineScore['riskLevel'] ?? null,
            'risk_level_after' => $followUpScore['riskLevel'] ?? null,
            'risk_reduction_pct' => (float) ($report['riskReductionPercent'] ?? 0.0),
            'direction' => (string) ($report['direction'] ?? 'unchanged'),
            'score_diff' => $report['scoreDiff'] ?? [],
            'body_regions_improved' => $report['bodyRegionsImproved'] ?? [],
            'body_regions_worsened' => $report['bodyRegionsWorsened'] ?? [],
            'completed_actions' => $completedActions,
            'corrective_action_summary' => $correctiveAction,
            'corrective_action_uuid' => $report['correctiveActionUuid'] ?? null,
            'status' => (string) ($report['status'] ?? 'generated'),
            'generated_at' => $report['generatedAt'] ?? null,
            'locked_at' => $report['lockedAt'] ?? null,
            'evidence_chain' => $report['evidenceChain'] ?? [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $regions
     * @return array<string, int>
     */
    private function bodyRegionScoreMap(array $regions): array
    {
        $scores = [];
        foreach ($regions as $region) {
            $name = strtolower((string) ($region['region'] ?? 'unknown'));
            $scores[$name] = (int) ($region['intensity'] ?? 0);
        }

        ksort($scores);

        return $scores;
    }

    /**
     * @param array<string, mixed> $report
     * @return list<string>
     */
    private function completedActions(array $report): array
    {
        $actionUuid = $report['correctiveActionUuid'] ?? null;
        if (!is_string($actionUuid) || $actionUuid === '' || $this->correctiveActions === null) {
            return [];
        }

        $action = $this->correctiveActions->findActionByUuid($actionUuid);
        if ($action === null) {
            return [];
        }

        $items = [$action->title];
        foreach ($this->correctiveActions->listEvidenceByActionUuid($actionUuid) as $evidence) {
            $type = (string) ($evidence['evidenceType'] ?? $evidence['evidence_type'] ?? 'evidence');
            $items[] = 'Evidence uploaded: ' . $type;
        }

        return $items;
    }

    /** @param array<string, mixed> $report */
    private function correctiveActionSummary(array $report): ?array
    {
        $actionUuid = $report['correctiveActionUuid'] ?? null;
        if (!is_string($actionUuid) || $actionUuid === '' || $this->correctiveActions === null) {
            return null;
        }

        $action = $this->correctiveActions->findActionByUuid($actionUuid);
        if ($action === null) {
            return null;
        }

        return [
            'uuid' => $action->uuid,
            'title' => $action->title,
            'status' => $action->status,
            'description' => $action->description,
            'reason' => $action->reason,
            'controlType' => $action->controlType,
            'hierarchyLevel' => $action->hierarchyLevel,
            'priority' => $action->priority,
            'dueDate' => $action->dueDate,
            'followUpAssessmentDueDate' => $action->followUpAssessmentDueDate,
            'completedAt' => $action->completedAt,
            'verifiedAt' => $action->verifiedAt,
            'evidenceCount' => count($this->correctiveActions->listEvidenceByActionUuid($actionUuid)),
            'historyCount' => count($this->correctiveActions->listStatusHistoryByActionUuid($actionUuid)),
        ];
    }

    /** @param array<string, mixed> $assessment */
    private function primaryThumbnailUuid(array $assessment): ?string
    {
        $videos = is_array($assessment['videos'] ?? null) ? $assessment['videos'] : [];
        foreach ($videos as $video) {
            if (!is_array($video)) {
                continue;
            }

            $uuid = trim((string) ($video['thumbnailStorageFileUuid'] ?? ''));
            if ($uuid !== '') {
                return $uuid;
            }
        }

        return null;
    }

    public function auditTrailReport(string $uuid): array
    {
        return [
            'uuid' => $uuid,
            'organizationUuid' => 'workeddy-demo-org',
            'organization' => 'WorkEddy Manufacturing Ltd',
            'logs' => [
                [
                    'timestamp' => '2026-07-01 10:15:30',
                    'user' => 'John Doe (CPE)',
                    'action' => 'Reviewed Assessment',
                    'details' => 'Adjusted initial AI trunk score from 4 to 5 based on persistent awkward flexion.',
                ],
                [
                    'timestamp' => '2026-07-01 10:16:00',
                    'user' => 'John Doe (CPE)',
                    'action' => 'Locked Score',
                    'details' => 'Assessment locked and finalized for reporting.',
                ],
            ],
        ];
    }

    public function pilotSummary(?string $organizationUuid = null, array $filters = []): array
    {
        $filters = $this->normalizePilotFilters($filters);
        $pilotSiteScope = $this->pilotSiteScope($organizationUuid, $filters);
        $assessmentScope = $this->assessmentScope($organizationUuid, $filters);
        $comparisonScope = $this->comparisonScope($organizationUuid, $filters);
        $actionScope = $this->actionScope($organizationUuid, $filters);
        $feedbackScope = $this->feedbackScope($organizationUuid, $filters);
        $supervisorScope = $this->supervisorScope($organizationUuid, $filters);
        $videoScope = $this->videoScope($organizationUuid, $filters);
        $validationAgreement = $this->validationAgreementSummary($organizationUuid, $filters);
        $supervisorSummary = $this->supervisorSummary($supervisorScope['where'], $supervisorScope['params']);

        $assessmentCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM assessments a' . $assessmentScope['join'] . $assessmentScope['where'],
            $assessmentScope['params']
        );
        $reviewedAssessmentCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM assessments a' . $assessmentScope['join'] . $assessmentScope['whereAnd'] . "a.status IN ('reviewed','locked')",
            $assessmentScope['params']
        );
        $baselineCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM assessments a' . $assessmentScope['join'] . $assessmentScope['whereAnd'] . 'a.is_baseline = 1',
            $assessmentScope['params']
        );
        $comparisonCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM comparison_reports cr' . $comparisonScope['join'] . $comparisonScope['where'],
            $comparisonScope['params']
        );
        $actionTotal = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM corrective_actions ca' . $actionScope['join'] . $actionScope['where'],
            $actionScope['params']
        );
        $actionCompleted = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM corrective_actions ca' . $actionScope['join'] . $actionScope['whereAnd'] . "ca.status IN ('completed','verified')",
            $actionScope['params']
        );
        $actionOverdue = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM corrective_actions ca' . $actionScope['join'] . $actionScope['whereAnd'] . "(
                ca.status = 'overdue'
                OR (ca.status IN ('open','assigned','in_progress') AND ca.due_date IS NOT NULL AND ca.due_date < CURDATE())
            )",
            $actionScope['params']
        );
        $feedbackSummary = $this->feedbackSummary($feedbackScope['where'], $feedbackScope['params']);

        $worksitesEnrolled = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM pilot_sites ps' . $pilotSiteScope['where'],
            $pilotSiteScope['params']
        );
        $workersParticipating = (int) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(ps.actual_worker_count), 0) FROM pilot_sites ps' . $pilotSiteScope['where'],
            $pilotSiteScope['params']
        );
        $taskVideosUploaded = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM assessment_videos av' . $videoScope['join'] . $videoScope['where'],
            $videoScope['params']
        );
        $highRiskTasks = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM assessments a' . $assessmentScope['join'] . $assessmentScope['whereAnd'] . "(
                a.final_score_json LIKE :highRiskUpper
                OR a.final_score_json LIKE :highRiskLower
                OR a.initial_score_json LIKE :highRiskUpper
                OR a.initial_score_json LIKE :highRiskLower
            )",
            array_merge($assessmentScope['params'], [
                'highRiskUpper' => '%High%',
                'highRiskLower' => '%high%',
            ])
        );
        $actionsAssigned = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM corrective_actions ca' . $actionScope['join'] . $actionScope['whereAnd'] . 'ca.assigned_to_user_id IS NOT NULL',
            $actionScope['params']
        );
        $averageClosureDays = (float) $this->connection->fetchOne(
            'SELECT ROUND(AVG(DATEDIFF(COALESCE(ca.verified_at, ca.completed_at), ca.created_at)), 2)
             FROM corrective_actions ca' . $actionScope['join'] . $actionScope['whereAnd'] . "ca.status IN ('completed','verified')",
            $actionScope['params']
        );
        $averageRiskReduction = (float) $this->connection->fetchOne(
            'SELECT ROUND(AVG(cr.risk_reduction_percent), 2)
             FROM comparison_reports cr' . $comparisonScope['join'] . $comparisonScope['where'],
            $comparisonScope['params']
        );
        $summary = [
            'worksites_enrolled' => $worksitesEnrolled,
            'workers_participating' => $workersParticipating,
            'task_videos_uploaded' => $taskVideosUploaded,
            'assessments' => $assessmentCount,
            'reviewed_assessments' => $reviewedAssessmentCount,
            'baseline_assessments' => $baselineCount,
            'high_risk_tasks_identified' => $highRiskTasks,
            'comparison_reports' => $comparisonCount,
            'corrective_actions_total' => $actionTotal,
            'corrective_actions_assigned' => $actionsAssigned,
            'corrective_actions_completed' => $actionCompleted,
            'corrective_actions_overdue' => $actionOverdue,
            'average_closure_days' => $averageClosureDays,
            'average_risk_reduction_pct' => $averageRiskReduction,
            'worker_feedback_total' => (int) ($feedbackSummary['totalResponses'] ?? 0),
            'worker_feedback_anonymous_rate' => (float) ($feedbackSummary['anonymousRate'] ?? 0.0),
            'average_discomfort' => (float) ($feedbackSummary['averageDiscomfort'] ?? 0.0),
            'average_pain_30' => (float) ($feedbackSummary['averagePain30Day'] ?? 0.0),
            'supervisor_feedback_total' => (int) ($supervisorSummary['totalResponses'] ?? 0),
            'supervisor_average_severity' => (float) ($supervisorSummary['averageSeverity'] ?? 0.0),
            'reviewer_agreement_rate' => (float) ($validationAgreement['overallAgreementRate'] ?? 0.0),
            'reviewer_pair_count' => (int) ($validationAgreement['pairCount'] ?? 0),
        ];

        return [
            'uuid' => $organizationUuid ?? 'pilot-summary',
            'organizationUuid' => $organizationUuid,
            'filters' => $filters,
            'summary' => $summary,
            'implementation_metrics' => [
                ['label' => 'Worksites Enrolled', 'value' => $summary['worksites_enrolled']],
                ['label' => 'Workers Participating', 'value' => $summary['workers_participating']],
                ['label' => 'Task Videos Uploaded', 'value' => $summary['task_videos_uploaded']],
                ['label' => 'Assessments Completed', 'value' => $summary['assessments']],
                ['label' => 'Corrective Actions Assigned', 'value' => $summary['corrective_actions_assigned']],
                ['label' => 'Corrective Actions Completed', 'value' => $summary['corrective_actions_completed']],
                ['label' => 'Corrective Actions Overdue', 'value' => $summary['corrective_actions_overdue']],
            ],
            'outcome_metrics' => [
                ['label' => 'High Risk Tasks Identified', 'value' => $summary['high_risk_tasks_identified']],
                ['label' => 'Comparison Reports', 'value' => $summary['comparison_reports']],
                ['label' => 'Average Closure Days', 'value' => $summary['average_closure_days']],
                ['label' => 'Average Risk Reduction %', 'value' => $summary['average_risk_reduction_pct']],
                ['label' => 'Average Discomfort', 'value' => $summary['average_discomfort']],
                ['label' => 'Supervisor Feedback Total', 'value' => $summary['supervisor_feedback_total']],
                ['label' => 'Reviewer Agreement Rate %', 'value' => $summary['reviewer_agreement_rate']],
            ],
            'top_body_regions' => $this->connection->fetchAllAssociative(
                'SELECT wf.body_region AS bodyRegion, COUNT(*) AS responses, ROUND(AVG(wf.discomfort_level), 2) AS averageDiscomfort
                 FROM worker_feedback wf' . $feedbackScope['where'] . '
                 GROUP BY body_region
                 ORDER BY COUNT(*) DESC, body_region ASC
                 LIMIT 5',
                $feedbackScope['params']
            ),
            'top_tasks' => $this->connection->fetchAllAssociative(
                'SELECT wf.task_uuid AS taskUuid, COALESCE(t.name, wf.task_uuid, \'Unlinked\') AS taskName, COUNT(*) AS responses,
                        ROUND(AVG(wf.discomfort_level), 2) AS averageDiscomfort
                 FROM worker_feedback wf
                 LEFT JOIN tasks t ON t.uuid = wf.task_uuid
                 ' . $feedbackScope['where'] . '
                 GROUP BY wf.task_uuid, taskName
                 ORDER BY COUNT(*) DESC, taskName ASC
                 LIMIT 5',
                $feedbackScope['params']
            ),
            'timeline' => $this->connection->fetchAllAssociative(
                'SELECT DATE(wf.created_at) AS date, COUNT(*) AS responses, ROUND(AVG(wf.discomfort_level), 2) AS averageDiscomfort
                 FROM worker_feedback wf' . $feedbackScope['where'] . '
                 GROUP BY DATE(wf.created_at)
                 ORDER BY DATE(wf.created_at) ASC
                 LIMIT 30',
                $feedbackScope['params']
            ),
            'supervisor_summary' => $supervisorSummary,
            'supervisor_top_body_regions' => $this->connection->fetchAllAssociative(
                'SELECT sf.body_region AS bodyRegion, COUNT(*) AS responses, ROUND(AVG(sf.severity_level), 2) AS averageSeverity
                 FROM supervisor_feedback sf' . $supervisorScope['where'] . '
                 GROUP BY sf.body_region
                 ORDER BY COUNT(*) DESC, sf.body_region ASC
                 LIMIT 5',
                $supervisorScope['params']
            ),
            'supervisor_timeline' => $this->connection->fetchAllAssociative(
                'SELECT DATE(sf.created_at) AS date, COUNT(*) AS responses, ROUND(AVG(sf.severity_level), 2) AS averageSeverity
                 FROM supervisor_feedback sf' . $supervisorScope['where'] . '
                 GROUP BY DATE(sf.created_at)
                 ORDER BY DATE(sf.created_at) ASC
                 LIMIT 30',
                $supervisorScope['params']
            ),
            'validation_agreement' => $validationAgreement,
        ];
    }

    /**
     * Impact Tracker snapshot.
     *
     * Returns two clearly separated blocks:
     *   - 'observed'  : counted directly from platform activity (assessments,
     *                   corrective_actions, comparison_reports, worker_feedback,
     *                   tasks, worksites/departments). No projection involved.
     *   - 'estimated' : planning approximations derived from the observed
     *                   'high_risk_tasks_reduced' figure and editable assumption
     *                   rates (see ReportingSettings::impact*). These are always
     *                   labeled as estimates/potential figures and must never be
     *                   presented as guaranteed outcomes.
     *
     * @return array<string, mixed>
     */
    public function impactTracker(?string $organizationUuid = null, array $filters = []): array
    {
        $filters = $this->normalizePilotFilters($filters);
        $assessmentScope = $this->assessmentScope($organizationUuid, $filters);
        $comparisonScope = $this->comparisonScope($organizationUuid, $filters);
        $actionScope = $this->actionScope($organizationUuid, $filters);
        $pilotSiteScope = $this->pilotSiteScope($organizationUuid, $filters);

        $highRiskPattern = "(
                a.final_score_json LIKE :highRiskUpper
                OR a.final_score_json LIKE :highRiskLower
                OR a.initial_score_json LIKE :highRiskUpper
                OR a.initial_score_json LIKE :highRiskLower
            )";
        $highRiskParams = array_merge($assessmentScope['params'], [
            'highRiskUpper' => '%High%',
            'highRiskLower' => '%high%',
        ]);

        // Observed: distinct tasks that have ever carried a High Risk assessment.
        $highRiskTasksIdentified = (int) $this->connection->fetchOne(
            'SELECT COUNT(DISTINCT a.task_uuid) FROM assessments a' . $assessmentScope['join'] . $assessmentScope['whereAnd'] . $highRiskPattern,
            $highRiskParams
        );

        // Observed: distinct tasks with a comparison report showing baseline High Risk
        // moving to a non-High Risk follow-up classification (an "observed improvement
        // after corrective action", not an assumption).
        $highRiskReducedPattern = "(
                (cr.baseline_score_json LIKE :highRiskUpper OR cr.baseline_score_json LIKE :highRiskLower)
                AND NOT (cr.follow_up_score_json LIKE :highRiskUpper OR cr.follow_up_score_json LIKE :highRiskLower)
            )";
        $highRiskTasksReduced = (int) $this->connection->fetchOne(
            'SELECT COUNT(DISTINCT t.uuid) FROM comparison_reports cr' . $comparisonScope['join'] . $comparisonScope['whereAnd'] . $highRiskReducedPattern,
            array_merge($comparisonScope['params'], [
                'highRiskUpper' => '%High%',
                'highRiskLower' => '%high%',
            ])
        );

        // Observed: completed corrective actions (same definition used across Reporting).
        $correctiveActionsCompleted = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM corrective_actions ca' . $actionScope['join'] . $actionScope['whereAnd'] . "ca.status IN ('completed','verified')",
            $actionScope['params']
        );

        // Observed: mean risk_reduction_percent across generated comparison reports.
        $averageRiskReduction = (float) $this->connection->fetchOne(
            'SELECT ROUND(AVG(cr.risk_reduction_percent), 2) FROM comparison_reports cr' . $comparisonScope['join'] . $comparisonScope['where'],
            $comparisonScope['params']
        );

        // Observed: distinct departments with at least one comparison report showing
        // measurable risk reduction (risk_reduction_percent > 0).
        $departmentsImproved = (int) $this->connection->fetchOne(
            'SELECT COUNT(DISTINCT t.department_id) FROM comparison_reports cr' . $comparisonScope['join'] . $comparisonScope['whereAnd'] . 't.department_id IS NOT NULL AND cr.risk_reduction_percent > 0',
            $comparisonScope['params']
        );

        // Observed: aggregate enrolled worker count from pilot sites in scope
        // (no individual worker identifiers are counted or exposed here).
        $workersReached = (int) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(ps.actual_worker_count), 0) FROM pilot_sites ps' . $pilotSiteScope['where'],
            $pilotSiteScope['params']
        );

        // Observed: tasks carrying 2 or more High Risk assessments over time — a
        // caution signal that risk is recurring despite prior activity, not a success metric.
        $repeatHighRiskTasks = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM (
                SELECT a.task_uuid
                FROM assessments a' . $assessmentScope['join'] . $assessmentScope['whereAnd'] . $highRiskPattern . '
                GROUP BY a.task_uuid
                HAVING COUNT(*) >= 2
            ) repeat_high_risk',
            $highRiskParams
        );

        $injuryPreventionRate = $this->settings?->impactInjuryPreventionRate() ?? 0.15;
        $lostWorkdaysPerInjury = $this->settings?->impactLostWorkdaysPerInjury() ?? 8.0;
        $costPerLostWorkday = $this->settings?->impactCostPerLostWorkday() ?? 450.0;
        $disclaimer = $this->settings?->impactEstimateDisclaimer() ?? 'Estimated figures are planning approximations only, not guarantees of injuries prevented, workdays saved, or costs avoided.';

        // Estimated: planning approximations only. Every figure below is derived from
        // the OBSERVED 'high_risk_tasks_reduced' count multiplied by a disclosed,
        // editable assumption rate. None of this is a measured or guaranteed outcome.
        $potentialInjuriesPrevented = round($highRiskTasksReduced * $injuryPreventionRate, 2);
        $potentialLostWorkdaysAvoided = round($potentialInjuriesPrevented * $lostWorkdaysPerInjury, 1);
        $potentialCostSavings = round($potentialLostWorkdaysAvoided * $costPerLostWorkday, 2);

        $observed = [
            'high_risk_tasks_identified' => $highRiskTasksIdentified,
            'high_risk_tasks_reduced' => $highRiskTasksReduced,
            'corrective_actions_completed' => $correctiveActionsCompleted,
            'average_risk_reduction_pct' => $averageRiskReduction,
            'departments_improved' => $departmentsImproved,
            'workers_reached' => $workersReached,
            'repeat_high_risk_tasks' => $repeatHighRiskTasks,
        ];

        $estimated = [
            'potential_injuries_prevented' => $potentialInjuriesPrevented,
            'potential_lost_workdays_avoided' => $potentialLostWorkdaysAvoided,
            'potential_cost_savings' => $potentialCostSavings,
        ];

        return [
            'uuid' => $organizationUuid ?? 'impact-tracker',
            'organizationUuid' => $organizationUuid,
            'filters' => $filters,
            'observed' => $observed,
            'estimated' => $estimated,
            'assumptions' => [
                'injuryPreventionRate' => $injuryPreventionRate,
                'lostWorkdaysPerInjury' => $lostWorkdaysPerInjury,
                'costPerLostWorkday' => $costPerLostWorkday,
            ],
            'disclaimer' => $disclaimer,
        ];
    }

    public function dashboardOverview(?string $organizationUuid = null, array $filters = []): array
    {
        $filters = $this->normalizePilotFilters($filters);

        $pilotSiteScope = $this->pilotSiteScope($organizationUuid, $filters);
        $assessmentScope = $this->assessmentScope($organizationUuid, $filters);
        $comparisonScope = $this->comparisonScope($organizationUuid, $filters);
        $actionScope = $this->actionScope($organizationUuid, $filters);
        $feedbackScope = $this->feedbackScope($organizationUuid, $filters);
        $videoScope = $this->videoScope($organizationUuid, $filters);

        $assessmentCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM assessments a' . $assessmentScope['join'] . $assessmentScope['where'],
            $assessmentScope['params']
        );
        $reviewedAssessmentCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM assessments a' . $assessmentScope['join'] . $assessmentScope['whereAnd'] . "a.status IN ('reviewed','locked')",
            $assessmentScope['params']
        );
        $comparisonCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM comparison_reports cr' . $comparisonScope['join'] . $comparisonScope['where'],
            $comparisonScope['params']
        );
        $actionTotal = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM corrective_actions ca' . $actionScope['join'] . $actionScope['where'],
            $actionScope['params']
        );
        $actionCompleted = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM corrective_actions ca' . $actionScope['join'] . $actionScope['whereAnd'] . "ca.status IN ('completed','verified')",
            $actionScope['params']
        );
        $feedbackSummary = $this->feedbackSummary($feedbackScope['where'], $feedbackScope['params']);

        $worksitesEnrolled = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM pilot_sites ps' . $pilotSiteScope['where'],
            $pilotSiteScope['params']
        );
        $workersParticipating = (int) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(ps.actual_worker_count), 0) FROM pilot_sites ps' . $pilotSiteScope['where'],
            $pilotSiteScope['params']
        );
        $taskVideosUploaded = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM assessment_videos av' . $videoScope['join'] . $videoScope['where'],
            $videoScope['params']
        );
        $highRiskTasks = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM assessments a' . $assessmentScope['join'] . $assessmentScope['whereAnd'] . "(
                a.final_score_json LIKE :highRiskUpper
                OR a.final_score_json LIKE :highRiskLower
                OR a.initial_score_json LIKE :highRiskUpper
                OR a.initial_score_json LIKE :highRiskLower
            )",
            array_merge($assessmentScope['params'], [
                'highRiskUpper' => '%High%',
                'highRiskLower' => '%high%',
            ])
        );

        return [
            'uuid' => $organizationUuid ?? 'dashboard-overview',
            'organizationUuid' => $organizationUuid,
            'filters' => $filters,
            'summary' => [
                'worksites_enrolled' => $worksitesEnrolled,
                'workers_participating' => $workersParticipating,
                'task_videos_uploaded' => $taskVideosUploaded,
                'assessments' => $assessmentCount,
                'reviewed_assessments' => $reviewedAssessmentCount,
                'high_risk_tasks_identified' => $highRiskTasks,
                'comparison_reports' => $comparisonCount,
                'corrective_actions_total' => $actionTotal,
                'corrective_actions_completed' => $actionCompleted,
                'worker_feedback_total' => (int) ($feedbackSummary['totalResponses'] ?? 0),
                'worker_feedback_anonymous_rate' => (float) ($feedbackSummary['anonymousRate'] ?? 0.0),
                'average_discomfort' => (float) ($feedbackSummary['averageDiscomfort'] ?? 0.0),
            ],
            'timeline' => $this->connection->fetchAllAssociative(
                'SELECT DATE(wf.created_at) AS date, COUNT(*) AS responses, ROUND(AVG(wf.discomfort_level), 2) AS averageDiscomfort
                 FROM worker_feedback wf' . $feedbackScope['where'] . '
                 GROUP BY DATE(wf.created_at)
                 ORDER BY DATE(wf.created_at) ASC
                 LIMIT 30',
                $feedbackScope['params']
            ),
        ];
    }

    /** @return array<string, string> */
    private function normalizePilotFilters(array $filters): array
    {
        return [
            'industry' => trim((string) ($filters['industry'] ?? '')),
            'worksiteUuid' => trim((string) ($filters['worksiteUuid'] ?? '')),
            'departmentUuid' => trim((string) ($filters['departmentUuid'] ?? '')),
            'jobRoleUuid' => trim((string) ($filters['jobRoleUuid'] ?? '')),
            'bodyRegion' => trim((string) ($filters['bodyRegion'] ?? '')),
            'fromDate' => trim((string) ($filters['fromDate'] ?? '')),
            'toDate' => trim((string) ($filters['toDate'] ?? '')),
            'riskLevel' => trim((string) ($filters['riskLevel'] ?? '')),
        ];
    }

    /** @param array<string, string> $filters @return array{where:string,params:array<string,mixed>} */
    private function pilotSiteScope(?string $organizationUuid, array $filters): array
    {
        $where = ['ps.deleted_at IS NULL'];
        $params = [];

        if ($organizationUuid !== null && $organizationUuid !== '') {
            $where[] = 'ps.organization_uuid = :organizationUuid';
            $params['organizationUuid'] = $organizationUuid;
        }
        if ($filters['worksiteUuid'] !== '') {
            $where[] = 'ps.worksite_uuid = :worksiteUuid';
            $params['worksiteUuid'] = $filters['worksiteUuid'];
        }
        if ($filters['industry'] !== '') {
            $where[] = 'ps.industry = :industry';
            $params['industry'] = $filters['industry'];
        }

        return [
            'where' => $this->compileWhere($where),
            'params' => $params,
        ];
    }

    /** @param array<string, string> $filters @return array{join:string,where:string,whereAnd:string,params:array<string,mixed>} */
    private function assessmentScope(?string $organizationUuid, array $filters): array
    {
        $where = [];
        $params = [];

        if ($organizationUuid !== null && $organizationUuid !== '') {
            $where[] = 'a.organization_uuid = :organizationUuid';
            $params['organizationUuid'] = $organizationUuid;
        }

        $this->applyPilotTaskFilters($where, $params, $filters, 'a', 't', true);

        return [
            'join' => ' LEFT JOIN tasks t ON t.uuid = a.task_uuid',
            'where' => $this->compileWhere($where),
            'whereAnd' => $this->compileWhereAnd($where),
            'params' => $params,
        ];
    }

    /** @param array<string, string> $filters @return array{join:string,where:string,whereAnd:string,params:array<string,mixed>} */
    private function comparisonScope(?string $organizationUuid, array $filters): array
    {
        $where = [];
        $params = [];

        if ($organizationUuid !== null && $organizationUuid !== '') {
            $where[] = 'cr.organization_uuid = :organizationUuid';
            $params['organizationUuid'] = $organizationUuid;
        }

        $this->applyPilotTaskFilters($where, $params, $filters, 'ba', 't', true);

        return [
            'join' => ' LEFT JOIN assessments ba ON ba.uuid = cr.baseline_assessment_uuid LEFT JOIN tasks t ON t.uuid = ba.task_uuid',
            'where' => $this->compileWhere($where),
            'whereAnd' => $this->compileWhereAnd($where),
            'params' => $params,
        ];
    }

    /** @param array<string, string> $filters @return array{join:string,where:string,whereAnd:string,params:array<string,mixed>} */
    private function actionScope(?string $organizationUuid, array $filters): array
    {
        $where = [];
        $params = [];

        if ($organizationUuid !== null && $organizationUuid !== '') {
            $where[] = 'ca.organization_uuid = :organizationUuid';
            $params['organizationUuid'] = $organizationUuid;
        }

        $this->applyPilotTaskFilters($where, $params, $filters, 'a', 't', true);

        return [
            'join' => ' LEFT JOIN assessments a ON a.uuid = ca.assessment_uuid LEFT JOIN tasks t ON t.uuid = a.task_uuid',
            'where' => $this->compileWhere($where),
            'whereAnd' => $this->compileWhereAnd($where),
            'params' => $params,
        ];
    }

    /** @param array<string, string> $filters @return array{join:string,where:string,whereAnd:string,params:array<string,mixed>} */
    private function videoScope(?string $organizationUuid, array $filters): array
    {
        $where = [];
        $params = [];

        if ($organizationUuid !== null && $organizationUuid !== '') {
            $where[] = 'a.organization_uuid = :organizationUuid';
            $params['organizationUuid'] = $organizationUuid;
        }

        $this->applyPilotTaskFilters($where, $params, $filters, 'a', 't', true);

        return [
            'join' => ' LEFT JOIN assessments a ON a.id = av.assessment_id LEFT JOIN tasks t ON t.uuid = a.task_uuid',
            'where' => $this->compileWhere($where),
            'whereAnd' => $this->compileWhereAnd($where),
            'params' => $params,
        ];
    }

    /** @param array<string, string> $filters @return array{where:string,params:array<string,mixed>} */
    private function feedbackScope(?string $organizationUuid, array $filters): array
    {
        $where = [];
        $params = [];

        if ($organizationUuid !== null && $organizationUuid !== '') {
            $where[] = 'wf.organization_uuid = :organizationUuid';
            $params['organizationUuid'] = $organizationUuid;
        }
        if ($filters['fromDate'] !== '') {
            $where[] = 'wf.created_at >= :fromDate';
            $params['fromDate'] = $filters['fromDate'];
        }
        if ($filters['toDate'] !== '') {
            $where[] = 'wf.created_at <= :toDate';
            $params['toDate'] = $filters['toDate'];
        }
        if ($filters['worksiteUuid'] !== '') {
            $where[] = 'wf.worksite_uuid = :worksiteUuid';
            $params['worksiteUuid'] = $filters['worksiteUuid'];
        }
        if ($filters['departmentUuid'] !== '') {
            $where[] = 'wf.department_uuid = :departmentUuid';
            $params['departmentUuid'] = $filters['departmentUuid'];
        }
        if ($filters['jobRoleUuid'] !== '') {
            $where[] = 'wf.job_role_uuid = :jobRoleUuid';
            $params['jobRoleUuid'] = $filters['jobRoleUuid'];
        }
        if ($filters['bodyRegion'] !== '') {
            $where[] = 'wf.body_region = :bodyRegion';
            $params['bodyRegion'] = $filters['bodyRegion'];
        }
        if ($filters['industry'] !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM pilot_sites ps WHERE ps.organization_uuid = wf.organization_uuid AND ps.worksite_uuid = wf.worksite_uuid AND ps.deleted_at IS NULL AND ps.industry = :industry)';
            $params['industry'] = $filters['industry'];
        }

        return [
            'where' => $this->compileWhere($where),
            'params' => $params,
        ];
    }

    /** @param array<string, string> $filters @return array{where:string,params:array<string,mixed>} */
    private function supervisorScope(?string $organizationUuid, array $filters): array
    {
        $where = [];
        $params = [];

        if ($organizationUuid !== null && $organizationUuid !== '') {
            $where[] = 'sf.organization_uuid = :organizationUuid';
            $params['organizationUuid'] = $organizationUuid;
        }
        if ($filters['fromDate'] !== '') {
            $where[] = 'sf.created_at >= :fromDate';
            $params['fromDate'] = $filters['fromDate'];
        }
        if ($filters['toDate'] !== '') {
            $where[] = 'sf.created_at <= :toDate';
            $params['toDate'] = $filters['toDate'];
        }
        if ($filters['worksiteUuid'] !== '') {
            $where[] = 'sf.worksite_uuid = :worksiteUuid';
            $params['worksiteUuid'] = $filters['worksiteUuid'];
        }
        if ($filters['departmentUuid'] !== '') {
            $where[] = 'sf.department_uuid = :departmentUuid';
            $params['departmentUuid'] = $filters['departmentUuid'];
        }
        if ($filters['jobRoleUuid'] !== '') {
            $where[] = 'sf.job_role_uuid = :jobRoleUuid';
            $params['jobRoleUuid'] = $filters['jobRoleUuid'];
        }
        if ($filters['bodyRegion'] !== '') {
            $where[] = 'sf.body_region = :bodyRegion';
            $params['bodyRegion'] = $filters['bodyRegion'];
        }
        if ($filters['riskLevel'] !== '') {
            $where[] = 'sf.observed_risk_level = :riskLevel';
            $params['riskLevel'] = $filters['riskLevel'];
        }
        if ($filters['industry'] !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM pilot_sites ps WHERE ps.organization_uuid = sf.organization_uuid AND ps.worksite_uuid = sf.worksite_uuid AND ps.deleted_at IS NULL AND ps.industry = :industry)';
            $params['industry'] = $filters['industry'];
        }

        return [
            'where' => $this->compileWhere($where),
            'params' => $params,
        ];
    }

    /** @param list<string> $where @param array<string, mixed> $params @param array<string, string> $filters */
    private function applyPilotTaskFilters(array &$where, array &$params, array $filters, string $assessmentAlias, string $taskAlias, bool $bodyRegionOnAssessment): void
    {
        if ($filters['fromDate'] !== '') {
            $where[] = $assessmentAlias . '.created_at >= :fromDate';
            $params['fromDate'] = $filters['fromDate'];
        }
        if ($filters['toDate'] !== '') {
            $where[] = $assessmentAlias . '.created_at <= :toDate';
            $params['toDate'] = $filters['toDate'];
        }
        if ($filters['worksiteUuid'] !== '') {
            $where[] = $taskAlias . '.worksite_id IN (SELECT id FROM worksites WHERE uuid = :worksiteUuid)';
            $params['worksiteUuid'] = $filters['worksiteUuid'];
        }
        if ($filters['departmentUuid'] !== '') {
            $where[] = $taskAlias . '.department_id IN (SELECT id FROM departments WHERE uuid = :departmentUuid)';
            $params['departmentUuid'] = $filters['departmentUuid'];
        }
        if ($filters['jobRoleUuid'] !== '') {
            $where[] = $taskAlias . '.job_role_id IN (SELECT id FROM job_roles WHERE uuid = :jobRoleUuid)';
            $params['jobRoleUuid'] = $filters['jobRoleUuid'];
        }
        if ($filters['industry'] !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM pilot_sites ps WHERE ps.organization_id = ' . $assessmentAlias . '.organization_id AND ps.worksite_id = ' . $taskAlias . '.worksite_id AND ps.deleted_at IS NULL AND ps.industry = :industry)';
            $params['industry'] = $filters['industry'];
        }
        if ($filters['bodyRegion'] !== '' && $bodyRegionOnAssessment) {
            $where[] = 'EXISTS (SELECT 1 FROM assessment_body_regions abr WHERE abr.assessment_id = ' . $assessmentAlias . '.id AND abr.region = :bodyRegion)';
            $params['bodyRegion'] = $filters['bodyRegion'];
        }
        if ($filters['riskLevel'] !== '') {
            $where[] = '(' . $assessmentAlias . '.final_score_json LIKE :riskLevelPattern OR ' . $assessmentAlias . '.initial_score_json LIKE :riskLevelPattern)';
            $params['riskLevelPattern'] = '%' . $filters['riskLevel'] . '%';
        }
    }

    /** @param list<string> $where */
    private function compileWhere(array $where): string
    {
        return $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    }

    /** @param list<string> $where */
    private function compileWhereAnd(array $where): string
    {
        return $where === [] ? ' WHERE ' : ' WHERE ' . implode(' AND ', $where) . ' AND ';
    }

    /** @param list<mixed> $params @return array<string, mixed> */
    private function supervisorSummary(string $where, array $params): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS totalResponses,
                    ROUND(AVG(severity_level), 2) AS averageSeverity,
                    ROUND(AVG(frequency_level), 2) AS averageFrequency
             FROM supervisor_feedback sf' . $where,
            $params
        );

        return [
            'totalResponses' => (int) ($row['totalResponses'] ?? 0),
            'averageSeverity' => (float) ($row['averageSeverity'] ?? 0.0),
            'averageFrequency' => (float) ($row['averageFrequency'] ?? 0.0),
        ];
    }

    /** @param array<string, string> $filters @return array<string, mixed> */
    private function validationAgreementSummary(?string $organizationUuid, array $filters): array
    {
        $where = [];
        $params = [];

        if ($organizationUuid !== null && $organizationUuid !== '') {
            $where[] = 'vr.organization_uuid = :organizationUuid';
            $params['organizationUuid'] = $organizationUuid;
        }

        $this->applyPilotTaskFilters($where, $params, $filters, 'a', 't', true);
        $where[] = 'vr.is_final = 1';

        $rows = $this->connection->fetchAllAssociative(
            'SELECT vr.* FROM validation_reviews vr
             LEFT JOIN assessments a ON a.uuid = vr.assessment_uuid
             LEFT JOIN tasks t ON t.uuid = a.task_uuid' . $this->compileWhere($where) . '
             ORDER BY vr.assessment_uuid ASC, vr.submitted_at ASC',
            $params
        );

        $reviews = array_map(fn(array $row): ValidationReview => $this->mapValidationReview($row), $rows);

        return (new ValidationAgreementService())->summarize($reviews);
    }

    /** @param array<string, mixed> $row */
    private function mapValidationReview(array $row): ValidationReview
    {
        return new ValidationReview(
            id: isset($row['id']) ? (int) $row['id'] : null,
            uuid: (string) ($row['uuid'] ?? ''),
            organizationId: (int) ($row['organization_id'] ?? 0),
            organizationUuid: (string) ($row['organization_uuid'] ?? ''),
            assessmentUuid: (string) ($row['assessment_uuid'] ?? ''),
            assessmentVersion: (string) ($row['assessment_version'] ?? ''),
            reviewerUserId: (int) ($row['reviewer_user_id'] ?? 0),
            reviewerName: (string) ($row['reviewer_name'] ?? ''),
            reviewerCredentials: $row['reviewer_credentials'] ?? null,
            reviewRound: (int) ($row['review_round'] ?? 1),
            score: $this->decodeJsonArray($row['score_json'] ?? null),
            riskLevel: (string) ($row['risk_level'] ?? ''),
            bodyRegions: array_values(array_map('strval', $this->decodeJsonArray($row['body_regions_json'] ?? null))),
            riskFactors: array_values(array_map('strval', $this->decodeJsonArray($row['risk_factors_json'] ?? null))),
            notes: $row['notes'] ?? null,
            isPrimary: (bool) ($row['is_primary'] ?? false),
            isFinal: (bool) ($row['is_final'] ?? true),
            submittedAt: isset($row['submitted_at']) ? (string) $row['submitted_at'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }

    /** @return array<string, mixed> */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $view
     * @return array<string, mixed>
     */
    private function buildAssessmentReport(array $view): array
    {
        $finalScore = is_array($view['finalScore'] ?? null) ? $view['finalScore'] : [];
        $review = is_array($view['review'] ?? null) ? $view['review'] : [];
        $videos = is_array($view['videos'] ?? null) ? $view['videos'] : [];
        $primaryVideo = is_array($videos[0] ?? null) ? $videos[0] : [];
        $aiAssistance = is_array($view['aiAssistance'] ?? null) ? $view['aiAssistance'] : [];
        $reviewPublished = in_array((string) ($view['status'] ?? ''), ['reviewed', 'locked'], true)
            && (string) ($view['scoreSource'] ?? '') === 'reviewer_confirmed'
            && $finalScore !== [];

        return [
            'uuid' => (string) ($view['uuid'] ?? ''),
            'organizationUuid' => (string) ($view['organizationUuid'] ?? ''),
            'organization' => (string) ($view['organizationUuid'] ?? ''),
            'worksite' => (string) ($view['organizationUuid'] ?? 'Unknown worksite'),
            'task' => (string) ($view['taskUuid'] ?? 'Unknown task'),
            'task_uuid' => $view['taskUuid'] ?? null,
            'date' => (string) ($view['createdAt'] ?? gmdate('Y-m-d H:i:s')),
            'assessor' => 'WorkEddy Assessor',
            'reviewer' => (string) ($review['reviewerName'] ?? 'Pending review'),
            'reviewer_notes' => $review['reviewerNotes'] ?? null,
            'adjustment_reason' => $review['adjustmentReason'] ?? null,
            'risk_score' => $reviewPublished ? (float) ($finalScore['raw'] ?? 0.0) : 0.0,
            'risk_level' => $reviewPublished ? (string) ($finalScore['riskLevel'] ?? 'Unclassified') : 'Pending reviewer confirmation',
            'assessment_status' => (string) ($view['status'] ?? 'draft'),
            'score_source' => $reviewPublished ? (string) ($view['scoreSource'] ?? 'unknown') : 'pending_reviewer_confirmation',
            'report_score_status' => $reviewPublished ? 'reviewer_confirmed' : 'awaiting_reviewer_confirmation',
            'body_region_scores' => $this->bodyRegionScoreMap($view['bodyRegions'] ?? []),
            'body_region_heatmap' => $view['bodyRegionHeatmap'] ?? [],
            'risk_factors' => array_values(array_map('strval', $view['riskFactors'] ?? [])),
            'recommendations' => [],
            'thumbnail_storage_file_uuid' => $primaryVideo['thumbnailStorageFileUuid'] ?? null,
            'pose_video_storage_file_uuid' => $primaryVideo['poseVideoStorageFileUuid'] ?? null,
            'blurred_video_storage_file_uuid' => $primaryVideo['blurredStorageFileUuid'] ?? null,
            'ai_advisory' => [
                'available' => (bool) ($aiAssistance['available'] ?? false),
                'advisory_only' => true,
                'message' => $reviewPublished
                    ? 'Reviewer-confirmed score published. AI estimate retained as support evidence.'
                    : 'AI estimate is retained as support evidence and is not published as the report score.',
                'score' => $aiAssistance['score'] ?? null,
                'confidence' => $aiAssistance['confidence'] ?? null,
                'model_version' => $aiAssistance['modelVersion'] ?? null,
                'flags' => $aiAssistance['flags'] ?? [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $action
     * @param list<array<string, mixed>> $evidence
     * @param list<array<string, mixed>> $history
     * @return array<string, mixed>
     */
    private function buildCorrectiveActionReport(array $action, array $evidence, array $history): array
    {
        $evidenceSummary = array_map(
            static fn(array $item): string => sprintf(
                '%s%s',
                (string) ($item['evidence_type'] ?? $item['evidenceType'] ?? 'evidence'),
                isset($item['storage_file_uuid']) || isset($item['storageFileUuid'])
                    ? ' (' . (string) ($item['storage_file_uuid'] ?? $item['storageFileUuid']) . ')'
                    : ''
            ),
            $evidence,
        );

        return [
            'uuid' => (string) ($action['uuid'] ?? ''),
            'organizationUuid' => (string) ($action['organizationUuid'] ?? ''),
            'organization' => (string) ($action['organizationUuid'] ?? ''),
            'actions' => [[
                'id' => 1,
                'title' => (string) ($action['title'] ?? ''),
                'status' => (string) ($action['status'] ?? ''),
                'assignee' => isset($action['assignedToUserId']) ? 'User #' . $action['assignedToUserId'] : 'Unassigned',
                'due_date' => $action['dueDate'] ?? null,
                'evidence' => $evidenceSummary === [] ? 'None uploaded' : implode('; ', $evidenceSummary),
            ]],
            'history' => $history,
        ];
    }

    /**
     * @param list<mixed> $params
     * @return array<string, mixed>
     */
    private function feedbackSummary(string $where, array $params): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS totalResponses,
                    SUM(CASE WHEN anonymous_status = 1 THEN 1 ELSE 0 END) AS anonymousResponses,
                    ROUND(AVG(discomfort_level), 2) AS averageDiscomfort,
                    ROUND(AVG(pain_7_day_level), 2) AS averagePain7Day,
                    ROUND(AVG(pain_30_day_level), 2) AS averagePain30Day
             FROM worker_feedback wf' . $where,
            $params
        );

        $total = (int) ($row['totalResponses'] ?? 0);

        return [
            'totalResponses' => $total,
            'anonymousResponses' => (int) ($row['anonymousResponses'] ?? 0),
            'anonymousRate' => $total > 0 ? round((((int) ($row['anonymousResponses'] ?? 0)) / $total) * 100, 2) : 0.0,
            'averageDiscomfort' => (float) ($row['averageDiscomfort'] ?? 0.0),
            'averagePain7Day' => (float) ($row['averagePain7Day'] ?? 0.0),
            'averagePain30Day' => (float) ($row['averagePain30Day'] ?? 0.0),
        ];
    }
}
