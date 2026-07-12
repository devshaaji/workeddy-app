<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Application\DTOs;

final class ResearchExportPreview
{
    /**
     * @param list<array<string, mixed>> $includedColumns
     * @param list<string> $excludedFields
     * @param list<string> $transformations
     */
    public function __construct(
        public readonly string $dataset,
        public readonly string $format,
        public readonly array $includedColumns,
        public readonly array $excludedFields,
        public readonly array $transformations,
        public readonly int $estimatedRows,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'dataset' => $this->dataset,
            'format' => $this->format,
            'includedColumns' => $this->includedColumns,
            'excludedFields' => $this->excludedFields,
            'transformations' => $this->transformations,
            'estimatedRows' => $this->estimatedRows,
        ];
    }
}
