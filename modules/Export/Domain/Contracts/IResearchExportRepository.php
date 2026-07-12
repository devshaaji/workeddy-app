<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Domain\Contracts;

use WorkEddy\Modules\Export\Domain\ResearchExport;

interface IResearchExportRepository
{
    public function create(ResearchExport $export): int;

    public function update(ResearchExport $export): void;

    public function findByUuid(string $uuid): ?ResearchExport;

    public function findByStorageFileUuid(string $storageFileUuid): ?ResearchExport;

    /**
     * @return list<ResearchExport>
     */
    public function listByOrganizationUuid(string $organizationUuid, int $limit = 20): array;

    /**
     * @param array<string, string> $maps
     */
    public function replaceCodeMaps(string $exportUuid, string $entityType, array $maps): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAssessmentDataset(string $organizationUuid, array $filters, int $limit): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchWorkerFeedbackDataset(string $organizationUuid, array $filters, int $limit): array;

    public function countAssessmentDataset(string $organizationUuid, array $filters): int;

    public function countWorkerFeedbackDataset(string $organizationUuid, array $filters): int;
}
