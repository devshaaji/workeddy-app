<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Domain;

final class NationalStatistic
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly string $title,
        public readonly string $value,
        public readonly ?string $unit,
        public readonly string $category,
        public readonly ?string $industryRelevance,
        public readonly string $sourceName,
        public readonly int $sourceYear,
        public readonly string $sourceUrl,
        public readonly bool $isPublished,
        public readonly string $dateAdded,
        public readonly ?int $createdByUserId,
        public readonly ?int $updatedByUserId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'value' => $this->value,
            'unit' => $this->unit,
            'category' => $this->category,
            'industryRelevance' => $this->industryRelevance,
            'sourceName' => $this->sourceName,
            'sourceYear' => $this->sourceYear,
            'sourceUrl' => $this->sourceUrl,
            'isPublished' => $this->isPublished,
            'dateAdded' => $this->dateAdded,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
