<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Domain;

final class ResearchExport
{
    /**
     * @param array<string, mixed> $filters
     * @param list<array<string, mixed>> $columnSchema
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly int $organizationId,
        public readonly string $organizationUuid,
        public readonly string $dataset,
        public readonly string $format,
        public readonly string $status,
        public readonly array $filters,
        public readonly array $columnSchema,
        public readonly string $deidentificationProfile,
        public readonly ?string $storageFileUuid,
        public readonly ?int $rowCount,
        public readonly ?int $generatedByUserId,
        public readonly ?string $generatedAt,
        public readonly ?string $expiresAt,
    ) {}

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'uuid' => $this->uuid,
            'organizationId' => $this->organizationUuid,
            'dataset' => $this->dataset,
            'format' => $this->format,
            'status' => $this->status,
            'filters' => $this->filters,
            'columnSchema' => $this->columnSchema,
            'deidentificationProfile' => $this->deidentificationProfile,
            'storageFileUuid' => $this->storageFileUuid,
            'rowCount' => $this->rowCount,
            'generatedByUserId' => $this->generatedByUserId,
            'generatedAt' => $this->generatedAt,
            'expiresAt' => $this->expiresAt,
        ];
    }
}
