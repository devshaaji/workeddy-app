<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Application\Services;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Reporting\Domain\Contracts\IPlatformAggregateMetricRepository;
use WorkEddy\Platform\Clock\IClock;

/**
 * Computes and reads the platform-wide (cross-organization) metrics shown on
 * the National Importance dashboard's "Dynamic WorkEddy Data" layer.
 *
 * IMPORTANT: refresh() intentionally applies NO organization filter \u2014 this
 * is the one place in Reporting that aggregates across every tenant. It must
 * never return anything identifying a specific organization, worksite, or
 * individual; only counts, rankings, and averages.
 *
 * refresh() is invoked by cronjobs/national-metrics-refresh.php, not on page
 * load, so a dashboard figure cited today stays reproducible \u2014 the stored
 * generated_at is the citation date shown on the page and in the PDF export.
 */
final class PlatformAggregateMetricsService
{
    private const METRIC_INDUSTRIES_REPRESENTED = 'industries_represented';
    private const METRIC_WORKSITES_ASSESSED = 'worksites_assessed';
    private const METRIC_HIGH_RISK_TASKS_IDENTIFIED = 'high_risk_tasks_identified';
    private const METRIC_COMMON_HIGH_STRAIN_TASKS = 'common_high_strain_tasks';
    private const METRIC_BODY_REGION_BURDEN = 'body_region_burden';
    private const METRIC_COMMON_CORRECTIVE_ACTIONS = 'common_corrective_actions';
    private const METRIC_AVERAGE_RISK_REDUCTION = 'average_risk_reduction_after_correction';
    private const METRIC_WORKER_DISCOMFORT_TREND = 'worker_discomfort_trend';

    public function __construct(
        private readonly Connection $connection,
        private readonly IPlatformAggregateMetricRepository $metrics,
        private readonly IClock $clock,
    ) {}

    /**
     * Recompute every platform-wide metric and append a new dated snapshot
     * row for each. Safe to run repeatedly (e.g. nightly cron); each run
     * adds new rows rather than mutating prior ones.
     */
    public function refresh(): void
    {
        $generatedAt = $this->clock->now()->format('Y-m-d H:i:s');

        $this->metrics->store(
            self::METRIC_INDUSTRIES_REPRESENTED,
            'Number of Industries Represented',
            $this->countIndustriesRepresented(),
            null,
            null,
            null,
            $generatedAt,
        );

        $this->metrics->store(
            self::METRIC_WORKSITES_ASSESSED,
            'Number of Worksites Assessed',
            $this->countWorksitesAssessed(),
            null,
            null,
            null,
            $generatedAt,
        );

        $this->metrics->store(
            self::METRIC_HIGH_RISK_TASKS_IDENTIFIED,
            'Number of High-Risk Tasks Identified',
            $this->countHighRiskTasksIdentified(),
            null,
            null,
            null,
            $generatedAt,
        );

        $this->metrics->store(
            self::METRIC_COMMON_HIGH_STRAIN_TASKS,
            'Most Common High-Strain Tasks',
            $this->rankCommonHighStrainTasks(),
            null,
            null,
            null,
            $generatedAt,
        );

        $this->metrics->store(
            self::METRIC_BODY_REGION_BURDEN,
            'Most Common Body Regions Affected',
            $this->rankBodyRegionBurden(),
            null,
            null,
            null,
            $generatedAt,
        );

        $this->metrics->store(
            self::METRIC_COMMON_CORRECTIVE_ACTIONS,
            'Most Common Corrective Actions',
            $this->rankCommonCorrectiveActions(),
            null,
            null,
            null,
            $generatedAt,
        );

        $this->metrics->store(
            self::METRIC_AVERAGE_RISK_REDUCTION,
            'Average Risk Reduction After Correction',
            $this->averageRiskReductionAfterCorrection(),
            null,
            null,
            null,
            $generatedAt,
        );

        $this->metrics->store(
            self::METRIC_WORKER_DISCOMFORT_TREND,
            'Worker Discomfort Trend',
            $this->workerDiscomfortTrend(),
            null,
            null,
            null,
            $generatedAt,
        );
    }

    /**
     * The latest stored snapshot for every metric, shaped for the dashboard
     * view / PDF template. Falls back to a zero/empty value with
     * generatedAt = null for any metric that hasn't been computed yet
     * (e.g. immediately after the migration runs, before the first cron tick).
     *
     * @return array<string, mixed>
     */
    public function latestSnapshot(): array
    {
        $latest = $this->metrics->latestAll();

        $get = static fn(string $key, mixed $default): mixed => $latest[$key]['value'] ?? $default;
        $generatedAt = null;
        foreach ($latest as $row) {
            if ($generatedAt === null || $row['generatedAt'] > $generatedAt) {
                $generatedAt = $row['generatedAt'];
            }
        }

        return [
            'industriesRepresented' => (int) $get(self::METRIC_INDUSTRIES_REPRESENTED, 0),
            'worksitesAssessed' => (int) $get(self::METRIC_WORKSITES_ASSESSED, 0),
            'highRiskTasksIdentified' => (int) $get(self::METRIC_HIGH_RISK_TASKS_IDENTIFIED, 0),
            'commonHighStrainTasks' => $get(self::METRIC_COMMON_HIGH_STRAIN_TASKS, []),
            'bodyRegionBurden' => $get(self::METRIC_BODY_REGION_BURDEN, []),
            'commonCorrectiveActions' => $get(self::METRIC_COMMON_CORRECTIVE_ACTIONS, []),
            'averageRiskReductionAfterCorrection' => (float) $get(self::METRIC_AVERAGE_RISK_REDUCTION, 0.0),
            'workerDiscomfortTrend' => $get(self::METRIC_WORKER_DISCOMFORT_TREND, []),
            'generatedAt' => $generatedAt,
        ];
    }

    private function countIndustriesRepresented(): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(DISTINCT LOWER(TRIM(industry)))
             FROM pilot_sites
             WHERE industry IS NOT NULL
               AND TRIM(industry) != ''
               AND deleted_at IS NULL",
        );
    }

    private function countWorksitesAssessed(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(DISTINCT t.worksite_id)
             FROM assessments a
             INNER JOIN tasks t ON t.uuid = a.task_uuid
             WHERE a.deleted_at IS NULL AND t.worksite_id IS NOT NULL',
        );
    }

    private function countHighRiskTasksIdentified(): int
    {
        return count(array_column($this->highRiskTaskRows(), 'taskUuid'));
    }

    /** @return list<array{task: string, count: int}> */
    private function rankCommonHighStrainTasks(): array
    {
        $counts = [];
        foreach ($this->highRiskTaskRows() as $row) {
            $taskName = trim((string) ($row['taskName'] ?? ''));
            if ($taskName === '') {
                continue;
            }

            $counts[$taskName] = ($counts[$taskName] ?? 0) + 1;
        }

        arsort($counts);
        $result = [];
        foreach (array_slice($counts, 0, 10, true) as $task => $count) {
            $result[] = [
                'task' => $task,
                'count' => $count,
            ];
        }

        return $result;
    }

    /** @return list<array{region: string, count: int}> */
    private function rankBodyRegionBurden(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT abr.region AS region, COUNT(*) AS response_count
             FROM assessment_body_regions abr
             INNER JOIN assessments a ON a.id = abr.assessment_id
             WHERE a.deleted_at IS NULL
             GROUP BY abr.region
             ORDER BY response_count DESC
             LIMIT 10',
        );

        return array_map(static fn(array $row): array => [
            'region' => (string) $row['region'],
            'count' => (int) $row['response_count'],
        ], $rows);
    }

    /** @return list<array{title: string, count: int}> */
    private function rankCommonCorrectiveActions(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT status, COUNT(*) AS action_count
             FROM corrective_actions
             GROUP BY status',
        );

        $countsByStatus = [];
        foreach ($rows as $row) {
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            if ($status === '') {
                continue;
            }

            $countsByStatus[$status] = (int) ($row['action_count'] ?? 0);
        }

        $orderedStatuses = [
            'open' => 'Open',
            'assigned' => 'Assigned',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'verified' => 'Verified',
            'overdue' => 'Overdue',
            'rejected' => 'Rejected',
        ];

        $result = [];
        foreach ($orderedStatuses as $status => $label) {
            $count = $countsByStatus[$status] ?? 0;
            if ($count <= 0) {
                continue;
            }

            $result[] = [
                'status' => $status,
                'label' => $label,
                'count' => $count,
            ];
        }

        return $result;
    }

    private function averageRiskReductionAfterCorrection(): float
    {
        return (float) $this->connection->fetchOne(
            'SELECT ROUND(AVG(risk_reduction_percent), 2) FROM comparison_reports',
        );
    }

    /** @return list<array{month: string, responses: int, averageDiscomfort: float}> */
    private function workerDiscomfortTrend(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COUNT(*) AS responses,
                    ROUND(AVG(discomfort_level), 2) AS avg_discomfort
             FROM worker_feedback
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month DESC
             LIMIT 12",
        );

        $rows = array_reverse($rows);

        return array_map(static fn(array $row): array => [
            'month' => (string) $row['month'],
            'responses' => (int) $row['responses'],
            'averageDiscomfort' => (float) $row['avg_discomfort'],
        ], $rows);
    }

    /**
     * @return list<array{taskUuid:string,taskName:string}>
     */
    private function highRiskTaskRows(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT a.task_uuid, t.name AS task_name, a.initial_score_json, a.final_score_json
             FROM assessments a
             INNER JOIN tasks t ON t.uuid = a.task_uuid
             WHERE a.deleted_at IS NULL AND a.task_uuid IS NOT NULL',
        );

        $tasks = [];
        foreach ($rows as $row) {
            if (!$this->isHighRiskScoreJson($row['final_score_json'] ?? null)
                && !$this->isHighRiskScoreJson($row['initial_score_json'] ?? null)) {
                continue;
            }

            $taskUuid = trim((string) ($row['task_uuid'] ?? ''));
            if ($taskUuid === '') {
                continue;
            }

            $tasks[] = [
                'taskUuid' => $taskUuid,
                'taskName' => trim((string) ($row['task_name'] ?? '')),
            ];
        }

        return $tasks;
    }

    private function isHighRiskScoreJson(mixed $raw): bool
    {
        if (!is_string($raw) || trim($raw) === '') {
            return false;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return false;
        }

        $riskLevel = strtolower(trim((string) ($decoded['riskLevel'] ?? $decoded['risk_level'] ?? '')));
        $riskCategory = strtolower(trim((string) ($decoded['riskCategory'] ?? $decoded['risk_category'] ?? '')));

        return in_array($riskLevel, ['high', 'high risk', 'very high'], true)
            || in_array($riskCategory, ['high', 'very_high', 'very high'], true);
    }
}
