<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Reporting\Domain\Contracts\IReportArtifactRepository;
use WorkEddy\Modules\Reporting\Domain\ReportArtifact;

final class ReportArtifactRepository implements IReportArtifactRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function create(ReportArtifact $artifact): int
    {
        $this->connection->insert('report_artifacts', [
            'uuid' => $artifact->uuid,
            'organization_uuid' => $artifact->organizationUuid,
            'report_type' => $artifact->reportType,
            'source_uuid' => $artifact->sourceUuid,
            'previous_artifact_uuid' => $artifact->previousArtifactUuid,
            'regeneration_reason' => $artifact->regenerationReason,
            'format' => $artifact->format,
            'storage_file_uuid' => $artifact->storageFileUuid,
            'template_name' => $artifact->templateName,
            'template_version' => $artifact->templateVersion,
            'snapshot_hash' => $artifact->snapshotHash,
            'snapshot_payload' => json_encode($artifact->snapshotPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'generated_by_user_id' => $artifact->generatedByUserId,
            'generated_at' => $artifact->generatedAt,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function findByUuid(string $uuid): ?ReportArtifact
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM report_artifacts WHERE uuid = ? LIMIT 1', [$uuid]);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByStorageFileUuid(string $storageFileUuid): ?ReportArtifact
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM report_artifacts WHERE storage_file_uuid = ? LIMIT 1', [$storageFileUuid]);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function listByReportSource(string $reportType, ?string $sourceUuid, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        if ($sourceUuid === null || $sourceUuid === '') {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT * FROM report_artifacts WHERE report_type = ? AND source_uuid IS NULL ORDER BY generated_at DESC, id DESC LIMIT ' . $limit,
                [$reportType]
            );
        } else {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT * FROM report_artifacts WHERE report_type = ? AND source_uuid = ? ORDER BY generated_at DESC, id DESC LIMIT ' . $limit,
                [$reportType, $sourceUuid]
            );
        }

        return array_map(fn(array $row): ReportArtifact => $this->hydrate($row), $rows);
    }

    public function listVersionChain(string $artifactUuid, int $limit = 20): array
    {
        $current = $this->findByUuid($artifactUuid);
        if ($current === null) {
            return [];
        }

        $rows = $this->listByReportSource($current->reportType, $current->sourceUuid, $limit);
        $index = [];
        foreach ($rows as $row) {
            $index[$row->uuid] = $row;
        }

        $chain = [];
        $cursor = $current;
        while ($cursor !== null && count($chain) < $limit) {
            $chain[] = $cursor;
            $cursor = $cursor->previousArtifactUuid !== null ? ($index[$cursor->previousArtifactUuid] ?? null) : null;
        }

        return $chain;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ReportArtifact
    {
        return new ReportArtifact(
            id: isset($row['id']) ? (int) $row['id'] : null,
            uuid: (string) ($row['uuid'] ?? ''),
            organizationUuid: isset($row['organization_uuid']) ? (string) $row['organization_uuid'] : null,
            reportType: (string) ($row['report_type'] ?? ''),
            sourceUuid: isset($row['source_uuid']) ? (string) $row['source_uuid'] : null,
            previousArtifactUuid: isset($row['previous_artifact_uuid']) ? (string) $row['previous_artifact_uuid'] : null,
            regenerationReason: isset($row['regeneration_reason']) ? (string) $row['regeneration_reason'] : null,
            format: (string) ($row['format'] ?? ''),
            storageFileUuid: (string) ($row['storage_file_uuid'] ?? ''),
            templateName: (string) ($row['template_name'] ?? ''),
            templateVersion: (string) ($row['template_version'] ?? 'v1'),
            snapshotHash: (string) ($row['snapshot_hash'] ?? ''),
            snapshotPayload: is_array(json_decode((string) ($row['snapshot_payload'] ?? '{}'), true)) ? json_decode((string) ($row['snapshot_payload'] ?? '{}'), true) : [],
            generatedByUserId: isset($row['generated_by_user_id']) ? (int) $row['generated_by_user_id'] : null,
            generatedAt: (string) ($row['generated_at'] ?? ''),
        );
    }
}
