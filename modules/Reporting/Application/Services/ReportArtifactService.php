<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Application\Services;

use WorkEddy\Modules\Reporting\Domain\Contracts\IReportArtifactRepository;
use WorkEddy\Modules\Reporting\Domain\ReportArtifact;

final class ReportArtifactService
{
    public function __construct(private readonly IReportArtifactRepository $repository) {}

    /**
     * @param array<string, mixed> $snapshot
     */
    public function register(
        string $artifactUuid,
        string $reportType,
        ?string $sourceUuid,
        ?string $previousArtifactUuid,
        ?string $regenerationReason,
        string $format,
        string $storageFileUuid,
        string $templateName,
        string $templateVersion,
        array $snapshot,
        ?int $generatedByUserId = null,
    ): ReportArtifact {
        $artifact = new ReportArtifact(
            id: null,
            uuid: $artifactUuid,
            organizationUuid: $this->organizationUuid($snapshot),
            reportType: $reportType,
            sourceUuid: $sourceUuid,
            previousArtifactUuid: $previousArtifactUuid,
            regenerationReason: $regenerationReason,
            format: $format,
            storageFileUuid: $storageFileUuid,
            templateName: $templateName,
            templateVersion: $templateVersion,
            snapshotHash: hash('sha256', (string) json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            snapshotPayload: $snapshot,
            generatedByUserId: $generatedByUserId,
            generatedAt: gmdate('Y-m-d H:i:s'),
        );

        $this->repository->create($artifact);

        return $artifact;
    }

    /** @param array<string, mixed> $snapshot */
    private function organizationUuid(array $snapshot): ?string
    {
        $value = $snapshot['organizationUuid'] ?? $snapshot['organization_uuid'] ?? $snapshot['organization'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
