<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Application\Support;

final class ResearchExportColumnCatalog
{
    /** @return list<array<string, mixed>> */
    public function includedColumns(string $dataset): array
    {
        return match ($dataset) {
            'worker_feedback' => [
                ['key' => 'org_code', 'label' => 'Organization Code'],
                ['key' => 'site_code', 'label' => 'Worksite Code'],
                ['key' => 'department_code', 'label' => 'Department Code'],
                ['key' => 'job_role_code', 'label' => 'Job Role Code'],
                ['key' => 'task_code', 'label' => 'Task Code'],
                ['key' => 'assessment_code', 'label' => 'Assessment Code'],
                ['key' => 'worker_code', 'label' => 'Worker Code'],
                ['key' => 'feedback_code', 'label' => 'Feedback Code'],
                ['key' => 'submitted_date', 'label' => 'Submitted Date'],
                ['key' => 'anonymous_status', 'label' => 'Anonymous Status'],
                ['key' => 'body_region', 'label' => 'Body Region'],
                ['key' => 'has_discomfort', 'label' => 'Has Discomfort'],
                ['key' => 'discomfort_level', 'label' => 'Discomfort Level'],
                ['key' => 'frequency_level', 'label' => 'Frequency Level'],
                ['key' => 'difficulty_level', 'label' => 'Difficulty Level'],
                ['key' => 'reporting_comfort_level', 'label' => 'Reporting Comfort Level'],
                ['key' => 'pain_7_day_level', 'label' => 'Pain 7 Day Level'],
                ['key' => 'pain_30_day_level', 'label' => 'Pain 30 Day Level'],
            ],
            default => [
                ['key' => 'org_code', 'label' => 'Organization Code'],
                ['key' => 'site_code', 'label' => 'Worksite Code'],
                ['key' => 'department_code', 'label' => 'Department Code'],
                ['key' => 'job_role_code', 'label' => 'Job Role Code'],
                ['key' => 'task_code', 'label' => 'Task Code'],
                ['key' => 'assessment_code', 'label' => 'Assessment Code'],
                ['key' => 'assessment_date', 'label' => 'Assessment Date'],
                ['key' => 'assessment_status', 'label' => 'Assessment Status'],
                ['key' => 'is_baseline', 'label' => 'Is Baseline'],
                ['key' => 'model', 'label' => 'Assessment Model'],
                ['key' => 'final_score', 'label' => 'Final Score'],
                ['key' => 'risk_level', 'label' => 'Risk Level'],
                ['key' => 'score_source', 'label' => 'Score Source'],
                ['key' => 'risk_factors', 'label' => 'Risk Factors'],
                ['key' => 'body_region_scores', 'label' => 'Body Region Scores'],
                ['key' => 'worker_feedback_count', 'label' => 'Worker Feedback Count'],
                ['key' => 'avg_discomfort_level', 'label' => 'Avg Discomfort Level'],
            ],
        };
    }

    /** @return list<string> */
    public function excludedFields(string $dataset): array
    {
        return match ($dataset) {
            'worker_feedback' => [
                'submitted_by_user_id',
                'suggested_change',
                'metadata_json',
                'user email',
                'user full name',
                'phone',
                'street address',
            ],
            default => [
                'organization name',
                'task name',
                'worksite location',
                'reviewer_name',
                'reviewer_credentials',
                'reviewer_notes',
                'adjustment_reason',
                'raw video uuids',
                'thumbnail uuids',
                'pose video uuids',
                'blurred video uuids',
            ],
        };
    }

    /** @return list<string> */
    public function transformations(string $dataset): array
    {
        $items = [
            'Direct identifiers are removed.',
            'Stable export-local study codes replace entity uuids and names.',
            'Free-text fields are excluded from research output.',
        ];

        if ($dataset === 'worker_feedback') {
            $items[] = 'Worker identity is replaced with WORKER### where available.';
        }

        return $items;
    }
}
