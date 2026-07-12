<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance\Domain\Entities;

final class PayrollSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $periodKey,
        public readonly float $grossAmount,
        public readonly float $netAmount,
        public readonly string $currency,
        public readonly int $employeeCount,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public function toArray(bool $includeInternalId = false): array
    {
        $data = [
            'uuid' => $this->uuid,
            'period_key' => $this->periodKey,
            'gross_amount' => $this->grossAmount,
            'net_amount' => $this->netAmount,
            'currency' => $this->currency,
            'employee_count' => $this->employeeCount,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];

        if ($includeInternalId) {
            $data['id'] = $this->id;
        }

        return $data;
    }
}
