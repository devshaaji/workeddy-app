<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Application;

use WorkEddy\Modules\Reporting\Application\UseCases\GenerateCsv;
use WorkEddy\Modules\Reporting\Application\UseCases\GeneratePdf;
use WorkEddy\Modules\Reporting\Domain\Contracts\IReportArtifactRepository;
use WorkEddy\Modules\Reporting\Domain\ReportArtifact;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class RegenerateReportArtifactUseCase
{
    public function __construct(
        private readonly IReportArtifactRepository $artifacts,
        private readonly GeneratePdf $generatePdf,
        private readonly GenerateCsv $generateCsv,
        private readonly IAuditService $audit,
    ) {}

    public function execute(
        string $artifactUuid,
        UserContext $actor,
        ?string $reason = null,
        ?string $format = null,
    ): ReportArtifact {
        $artifact = $this->artifacts->findByUuid($artifactUuid);
        if ($artifact === null) {
            throw new NotFoundException('Report artifact not found.');
        }

        $targetFormat = strtolower(trim((string) ($format ?? $artifact->format)));
        $regenerationReason = $this->normalizeReason($reason);
        $storageFileUuid = match ($targetFormat) {
            'pdf' => $this->generatePdf->regenerate(
                $artifact->reportType,
                $artifact->sourceUuid,
                $artifact->organizationUuid,
                $artifact->uuid,
                $regenerationReason,
            ),
            'csv' => $this->generateCsv->regenerate(
                $artifact->reportType,
                $artifact->sourceUuid,
                $artifact->organizationUuid,
                $artifact->uuid,
                $regenerationReason,
            ),
            default => throw new \InvalidArgumentException('Unsupported report format for regeneration.'),
        };

        $newArtifact = $this->artifacts->findByStorageFileUuid($storageFileUuid);
        if ($newArtifact === null) {
            throw new \RuntimeException('Regenerated report artifact was not registered.');
        }

        $this->audit->record(
            'reporting.report.regenerated',
            'report_artifact',
            $newArtifact->uuid,
            actorId: (string) $actor->userId,
            actorType: 'user',
            metadata: [
                'previousArtifactUuid' => $artifact->uuid,
                'newStorageFileUuid' => $storageFileUuid,
                'reportType' => $artifact->reportType,
                'sourceUuid' => $artifact->sourceUuid,
                'format' => $targetFormat,
                'reason' => $regenerationReason,
            ],
        );

        return $newArtifact;
    }

    private function normalizeReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason !== '' ? $reason : null;
    }
}
