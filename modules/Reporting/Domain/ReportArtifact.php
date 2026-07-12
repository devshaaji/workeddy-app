<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Domain;

final class ReportArtifact
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly ?string $organizationUuid,
        public readonly string $reportType,
        public readonly ?string $sourceUuid,
        public readonly ?string $previousArtifactUuid,
        public readonly ?string $regenerationReason,
        public readonly string $format,
        public readonly string $storageFileUuid,
        public readonly string $templateName,
        public readonly string $templateVersion,
        public readonly string $snapshotHash,
        /** @var array<string, mixed> */
        public readonly array $snapshotPayload,
        public readonly ?int $generatedByUserId,
        public readonly string $generatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'uuid' => $this->uuid,
            'organizationUuid' => $this->organizationUuid,
            'reportType' => $this->reportType,
            'sourceUuid' => $this->sourceUuid,
            'previousArtifactUuid' => $this->previousArtifactUuid,
            'regenerationReason' => $this->regenerationReason,
            'format' => $this->format,
            'storageFileUuid' => $this->storageFileUuid,
            'templateName' => $this->templateName,
            'templateVersion' => $this->templateVersion,
            'snapshotHash' => $this->snapshotHash,
            'snapshotPayload' => $this->snapshotPayload,
            'generatedByUserId' => $this->generatedByUserId,
            'generatedAt' => $this->generatedAt,
        ];
    }
}
