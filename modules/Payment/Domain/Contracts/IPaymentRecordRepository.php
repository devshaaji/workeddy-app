<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Domain\Contracts;

use WorkEddy\Modules\Payment\Domain\Entities\PaymentRecord;

interface IPaymentRecordRepository
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): PaymentRecord;

    public function findById(int $id): ?PaymentRecord;

    public function findByUuid(string $uuid): ?PaymentRecord;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): PaymentRecord;

    /**
     * @param array<string, mixed> $filters
     * @return array<int, PaymentRecord>
     */
    public function list(array $filters = []): array;
}
