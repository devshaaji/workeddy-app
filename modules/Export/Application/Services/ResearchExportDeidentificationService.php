<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Application\Services;

use WorkEddy\Modules\Export\Application\Support\ResearchExportColumnCatalog;

final class ResearchExportDeidentificationService
{
    /** @var array<string, array<string, string>> */
    private array $maps = [];

    public function __construct(
        private readonly ResearchExportColumnCatalog $columns,
    ) {}

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{rows:list<array<string, scalar|null>>,codeMaps:array<string, array<string, string>>,columnSchema:list<array<string, mixed>>}
     */
    public function transform(string $dataset, array $rows): array
    {
        $this->maps = [];
        $result = [];

        foreach ($rows as $row) {
            $result[] = match ($dataset) {
                'worker_feedback' => $this->transformWorkerFeedbackRow($row),
                default => $this->transformAssessmentRow($row),
            };
        }

        return [
            'rows' => $result,
            'codeMaps' => $this->maps,
            'columnSchema' => $this->columns->includedColumns($dataset),
        ];
    }

    /** @param array<string, mixed> $row */
    private function transformAssessmentRow(array $row): array
    {
        return [
            'org_code' => $this->code('organization', (string) ($row['organization_uuid'] ?? '')),
            'site_code' => $this->nullableCode('worksite', $row['worksite_uuid'] ?? null),
            'department_code' => $this->nullableCode('department', $row['department_uuid'] ?? null),
            'job_role_code' => $this->nullableCode('job_role', $row['job_role_uuid'] ?? null),
            'task_code' => $this->nullableCode('task', $row['task_uuid'] ?? null),
            'assessment_code' => $this->code('assessment', (string) ($row['assessment_uuid'] ?? '')),
            'assessment_date' => substr((string) ($row['created_at'] ?? ''), 0, 10),
            'assessment_status' => (string) ($row['status'] ?? ''),
            'is_baseline' => (int) ($row['is_baseline'] ?? 0),
            'model' => (string) ($row['model'] ?? ''),
            'final_score' => $this->scoreValue($row['final_score_json'] ?? null, $row['initial_score_json'] ?? null),
            'risk_level' => $this->riskLevel($row['final_score_json'] ?? null, $row['initial_score_json'] ?? null),
            'score_source' => (string) ($row['score_source'] ?? ''),
            'risk_factors' => (string) ($row['risk_factors'] ?? ''),
            'body_region_scores' => (string) ($row['body_region_scores'] ?? ''),
            'worker_feedback_count' => (int) ($row['worker_feedback_count'] ?? 0),
            'avg_discomfort_level' => $row['avg_discomfort_level'] !== null ? (float) $row['avg_discomfort_level'] : null,
        ];
    }

    /** @param array<string, mixed> $row */
    private function transformWorkerFeedbackRow(array $row): array
    {
        return [
            'org_code' => $this->code('organization', (string) ($row['organization_uuid'] ?? '')),
            'site_code' => $this->nullableCode('worksite', $row['worksite_uuid'] ?? null),
            'department_code' => $this->nullableCode('department', $row['department_uuid'] ?? null),
            'job_role_code' => $this->nullableCode('job_role', $row['job_role_uuid'] ?? null),
            'task_code' => $this->nullableCode('task', $row['task_uuid'] ?? null),
            'assessment_code' => $this->nullableCode('assessment', $row['assessment_uuid'] ?? null),
            'worker_code' => $this->workerCode($row),
            'feedback_code' => $this->code('feedback', (string) ($row['feedback_uuid'] ?? '')),
            'submitted_date' => substr((string) ($row['created_at'] ?? ''), 0, 10),
            'anonymous_status' => (int) ($row['anonymous_status'] ?? 0),
            'body_region' => (string) ($row['body_region'] ?? ''),
            'has_discomfort' => (int) ($row['has_discomfort'] ?? 0),
            'discomfort_level' => (int) ($row['discomfort_level'] ?? 0),
            'frequency_level' => (int) ($row['frequency_level'] ?? 0),
            'difficulty_level' => (int) ($row['difficulty_level'] ?? 0),
            'reporting_comfort_level' => (int) ($row['reporting_comfort_level'] ?? 0),
            'pain_7_day_level' => (int) ($row['pain_7_day_level'] ?? 0),
            'pain_30_day_level' => (int) ($row['pain_30_day_level'] ?? 0),
        ];
    }

    /** @param mixed $value */
    private function nullableCode(string $entityType, mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $this->code($entityType, $value) : null;
    }

    private function workerCode(array $row): ?string
    {
        if ((int) ($row['anonymous_status'] ?? 0) === 1) {
            return null;
        }

        $userId = isset($row['submitted_by_user_id']) ? (string) $row['submitted_by_user_id'] : '';
        if ($userId === '') {
            return null;
        }

        return $this->code('worker', $userId);
    }

    private function code(string $entityType, string $entityKey): string
    {
        if ($entityKey === '') {
            return '';
        }
        if (!isset($this->maps[$entityType][$entityKey])) {
            $prefix = match ($entityType) {
                'organization' => 'ORG',
                'worksite' => 'SITE',
                'task' => 'TASK',
                'worker' => 'WORKER',
                'department' => 'DEPT',
                'job_role' => 'ROLE',
                'assessment' => 'ASSESS',
                default => strtoupper(substr($entityType, 0, 4)),
            };
            $index = count($this->maps[$entityType] ?? []) + 1;
            $this->maps[$entityType][$entityKey] = sprintf('%s%03d', $prefix, $index);
        }

        return $this->maps[$entityType][$entityKey];
    }

    /** @param mixed $final @param mixed $initial */
    private function scoreValue(mixed $final, mixed $initial): ?float
    {
        $data = $this->decodeJson($final) ?: $this->decodeJson($initial);

        return isset($data['raw']) ? (float) $data['raw'] : (isset($data['raw_score']) ? (float) $data['raw_score'] : null);
    }

    /** @param mixed $final @param mixed $initial */
    private function riskLevel(mixed $final, mixed $initial): ?string
    {
        $data = $this->decodeJson($final) ?: $this->decodeJson($initial);

        return isset($data['riskLevel']) ? (string) $data['riskLevel'] : (isset($data['risk_level']) ? (string) $data['risk_level'] : null);
    }

    /** @return array<string, mixed> */
    private function decodeJson(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
