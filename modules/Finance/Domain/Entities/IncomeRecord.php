<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance\Domain\Entities;

final class IncomeRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $sourceType,
        public readonly string $referenceNumber,
        public readonly string $category,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public function toArray(bool $includeInternalId = false): array
    {
        $data = [
            'uuid' => $this->uuid,
            'source_type' => $this->sourceType,
            'reference_number' => $this->referenceNumber,
            'category' => $this->category,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];

        if ($includeInternalId) {
            $data['id'] = $this->id;
        }

        return $data;
    }
}
