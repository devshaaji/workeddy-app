<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Domain\Contracts;

use WorkEddy\Modules\Reporting\Domain\ReportArtifact;

interface IReportArtifactRepository
{
    public function create(ReportArtifact $artifact): int;

    public function findByUuid(string $uuid): ?ReportArtifact;

    public function findByStorageFileUuid(string $storageFileUuid): ?ReportArtifact;

    /**
     * @return list<ReportArtifact>
     */
    public function listByReportSource(string $reportType, ?string $sourceUuid, int $limit = 20): array;

    /**
     * @return list<ReportArtifact>
     */
    public function listVersionChain(string $artifactUuid, int $limit = 20): array;
}
