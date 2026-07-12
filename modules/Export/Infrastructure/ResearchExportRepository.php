<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Export\Domain\Contracts\IResearchExportRepository;
use WorkEddy\Modules\Export\Domain\ResearchExport;

final class ResearchExportRepository implements IResearchExportRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function create(ResearchExport $export): int
    {
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->insert('research_exports', [
            'uuid' => $export->uuid,
            'organization_id' => $export->organizationId,
            'organization_uuid' => $export->organizationUuid,
            'dataset' => $export->dataset,
            'format' => $export->format,
            'status' => $export->status,
            'filters_json' => json_encode($export->filters, JSON_THROW_ON_ERROR),
            'column_schema_json' => json_encode($export->columnSchema, JSON_THROW_ON_ERROR),
            'deidentification_profile' => $export->deidentificationProfile,
            'storage_file_uuid' => $export->storageFileUuid,
            'row_count' => $export->rowCount,
            'generated_by_user_id' => $export->generatedByUserId,
            'generated_at' => $export->generatedAt,
            'expires_at' => $export->expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(ResearchExport $export): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->update('research_exports', [
            'dataset' => $export->dataset,
            'format' => $export->format,
            'status' => $export->status,
            'filters_json' => json_encode($export->filters, JSON_THROW_ON_ERROR),
            'column_schema_json' => json_encode($export->columnSchema, JSON_THROW_ON_ERROR),
            'deidentification_profile' => $export->deidentificationProfile,
            'storage_file_uuid' => $export->storageFileUuid,
            'row_count' => $export->rowCount,
            'generated_by_user_id' => $export->generatedByUserId,
            'generated_at' => $export->generatedAt,
            'expires_at' => $export->expiresAt,
            'updated_at' => $now,
        ], ['uuid' => $export->uuid]);
    }

    public function findByUuid(string $uuid): ?ResearchExport
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM research_exports WHERE uuid = ? LIMIT 1', [$uuid]);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByStorageFileUuid(string $storageFileUuid): ?ResearchExport
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM research_exports WHERE storage_file_uuid = ? LIMIT 1', [$storageFileUuid]);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function listByOrganizationUuid(string $organizationUuid, int $limit = 20): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM research_exports WHERE organization_uuid = ? ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)),
            [$organizationUuid]
        );

        return array_map(fn(array $row): ResearchExport => $this->hydrate($row), $rows);
    }

    public function replaceCodeMaps(string $exportUuid, string $entityType, array $maps): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->delete('research_export_code_maps', [
            'export_uuid' => $exportUuid,
            'entity_type' => $entityType,
        ]);

        foreach ($maps as $entityUuid => $exportCode) {
            $this->connection->insert('research_export_code_maps', [
                'export_uuid' => $exportUuid,
                'entity_type' => $entityType,
                'entity_uuid' => $entityUuid,
                'export_code' => $exportCode,
                'created_at' => $now,
            ]);
        }
    }

    public function fetchAssessmentDataset(string $organizationUuid, array $filters, int $limit): array
    {
        $params = ['org' => $organizationUuid];
        $where = ['a.organization_uuid = :org', 'a.deleted_at IS NULL'];
        $this->applyCommonFilters($where, $params, $filters, assessmentAlias: 'a', taskAlias: 't');

        $sql = 'SELECT a.uuid AS assessment_uuid, a.organization_uuid, a.task_uuid, a.model, a.initial_score_json, a.final_score_json,
                       a.status, a.is_baseline, a.score_source, a.created_at,
                       t.worksite_id, ws.uuid AS worksite_uuid,
                       t.department_id, d.uuid AS department_uuid,
                       t.job_role_id, jr.uuid AS job_role_uuid,
                       COALESCE(rf.factors, \'\') AS risk_factors,
                       COALESCE(br.regions, \'\') AS body_region_scores,
                       COALESCE(fb.feedback_count, 0) AS worker_feedback_count,
                       fb.avg_discomfort_level
                FROM assessments a
                INNER JOIN tasks t ON t.uuid = a.task_uuid
                LEFT JOIN worksites ws ON ws.id = t.worksite_id
                LEFT JOIN departments d ON d.id = t.department_id
                LEFT JOIN job_roles jr ON jr.id = t.job_role_id
                LEFT JOIN (
                    SELECT assessment_id, GROUP_CONCAT(factor_key ORDER BY factor_key SEPARATOR \';\') AS factors
                    FROM assessment_risk_factors
                    GROUP BY assessment_id
                ) rf ON rf.assessment_id = a.id
                LEFT JOIN (
                    SELECT assessment_id, GROUP_CONCAT(CONCAT(region, \':\', side, \':\', intensity) ORDER BY region SEPARATOR \';\') AS regions
                    FROM assessment_body_regions
                    GROUP BY assessment_id
                ) br ON br.assessment_id = a.id
                LEFT JOIN (
                    SELECT assessment_uuid, COUNT(*) AS feedback_count, ROUND(AVG(discomfort_level), 2) AS avg_discomfort_level
                    FROM worker_feedback
                    GROUP BY assessment_uuid
                ) fb ON fb.assessment_uuid = a.uuid
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.id DESC
                LIMIT ' . max(1, $limit);

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function fetchWorkerFeedbackDataset(string $organizationUuid, array $filters, int $limit): array
    {
        $params = ['org' => $organizationUuid];
        $where = ['wf.organization_uuid = :org'];
        $this->applyCommonFilters($where, $params, $filters, assessmentAlias: 'wf', taskAlias: 't', createdAlias: 'wf');

        $sql = 'SELECT wf.uuid AS feedback_uuid, wf.organization_uuid, wf.task_uuid, wf.assessment_uuid,
                       wf.worksite_uuid, wf.department_uuid, wf.job_role_uuid, wf.submitted_by_user_id,
                       wf.anonymous_status, wf.body_region, wf.has_discomfort, wf.discomfort_level,
                       wf.frequency_level, wf.difficulty_level, wf.reporting_comfort_level,
                       wf.pain_7_day_level, wf.pain_30_day_level, wf.created_at
                FROM worker_feedback wf
                LEFT JOIN tasks t ON t.uuid = wf.task_uuid
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY wf.id DESC
                LIMIT ' . max(1, $limit);

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function countAssessmentDataset(string $organizationUuid, array $filters): int
    {
        $params = ['org' => $organizationUuid];
        $where = ['a.organization_uuid = :org', 'a.deleted_at IS NULL'];
        $this->applyCommonFilters($where, $params, $filters, assessmentAlias: 'a', taskAlias: 't');

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM assessments a INNER JOIN tasks t ON t.uuid = a.task_uuid WHERE ' . implode(' AND ', $where),
            $params
        );
    }

    public function countWorkerFeedbackDataset(string $organizationUuid, array $filters): int
    {
        $params = ['org' => $organizationUuid];
        $where = ['wf.organization_uuid = :org'];
        $this->applyCommonFilters($where, $params, $filters, assessmentAlias: 'wf', taskAlias: 't', createdAlias: 'wf');

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM worker_feedback wf LEFT JOIN tasks t ON t.uuid = wf.task_uuid WHERE ' . implode(' AND ', $where),
            $params
        );
    }

    /**
     * @param list<string> $where
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filters
     */
    private function applyCommonFilters(array &$where, array &$params, array $filters, string $assessmentAlias, string $taskAlias, string $createdAlias = 'a'): void
    {
        $from = isset($filters['fromDate']) ? trim((string) $filters['fromDate']) : '';
        $to = isset($filters['toDate']) ? trim((string) $filters['toDate']) : '';
        $worksiteUuid = isset($filters['worksiteUuid']) ? trim((string) $filters['worksiteUuid']) : '';
        $departmentUuid = isset($filters['departmentUuid']) ? trim((string) $filters['departmentUuid']) : '';
        $jobRoleUuid = isset($filters['jobRoleUuid']) ? trim((string) $filters['jobRoleUuid']) : '';
        $taskUuid = isset($filters['taskUuid']) ? trim((string) $filters['taskUuid']) : '';
        $model = isset($filters['model']) ? trim((string) $filters['model']) : '';

        if ($from !== '') {
            $where[] = $createdAlias . '.created_at >= :fromDate';
            $params['fromDate'] = $from;
        }
        if ($to !== '') {
            $where[] = $createdAlias . '.created_at <= :toDate';
            $params['toDate'] = $to;
        }
        if ($worksiteUuid !== '') {
            $where[] = $taskAlias . '.worksite_id IN (SELECT id FROM worksites WHERE uuid = :worksiteUuid)';
            $params['worksiteUuid'] = $worksiteUuid;
        }
        if ($departmentUuid !== '') {
            $where[] = $taskAlias . '.department_id IN (SELECT id FROM departments WHERE uuid = :departmentUuid)';
            $params['departmentUuid'] = $departmentUuid;
        }
        if ($jobRoleUuid !== '') {
            $where[] = $taskAlias . '.job_role_id IN (SELECT id FROM job_roles WHERE uuid = :jobRoleUuid)';
            $params['jobRoleUuid'] = $jobRoleUuid;
        }
        if ($taskUuid !== '') {
            $where[] = $taskAlias . '.uuid = :taskUuid';
            $params['taskUuid'] = $taskUuid;
        }
        if ($model !== '' && $assessmentAlias === 'a') {
            $where[] = $assessmentAlias . '.model = :model';
            $params['model'] = $model;
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ResearchExport
    {
        $filters = is_string($row['filters_json'] ?? null) ? json_decode((string) $row['filters_json'], true) : [];
        $schema = is_string($row['column_schema_json'] ?? null) ? json_decode((string) $row['column_schema_json'], true) : [];

        return new ResearchExport(
            id: isset($row['id']) ? (int) $row['id'] : null,
            uuid: (string) ($row['uuid'] ?? ''),
            organizationId: (int) ($row['organization_id'] ?? 0),
            organizationUuid: (string) ($row['organization_uuid'] ?? ''),
            dataset: (string) ($row['dataset'] ?? 'assessments'),
            format: (string) ($row['format'] ?? 'csv'),
            status: (string) ($row['status'] ?? 'pending'),
            filters: is_array($filters) ? $filters : [],
            columnSchema: is_array($schema) ? $schema : [],
            deidentificationProfile: (string) ($row['deidentification_profile'] ?? 'research_default_v1'),
            storageFileUuid: isset($row['storage_file_uuid']) ? (string) $row['storage_file_uuid'] : null,
            rowCount: isset($row['row_count']) ? (int) $row['row_count'] : null,
            generatedByUserId: isset($row['generated_by_user_id']) ? (int) $row['generated_by_user_id'] : null,
            generatedAt: isset($row['generated_at']) ? (string) $row['generated_at'] : null,
            expiresAt: isset($row['expires_at']) ? (string) $row['expires_at'] : null,
        );
    }
}
