<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Domain\Contracts;

use WorkEddy\Modules\Billing\Domain\Entities\Quotation;

interface IQuotationRepository
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Quotation;

    public function findById(int $id): ?Quotation;

    public function findByUuid(string $uuid): ?Quotation;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): Quotation;

    public function archive(Quotation $quotation): void;

    /**
     * @param array<string, mixed> $filters
     * @return array<int, Quotation>
     */
    public function list(array $filters = []): array;
}
