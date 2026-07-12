<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance\Application\UseCases;

use WorkEddy\Modules\Finance\Domain\Contracts\IFinanceRepository;
use WorkEddy\Modules\Finance\Domain\Entities\IncomeRecord;
use WorkEddy\Shared\Exceptions\ValidationException;

final class RecordIncome
{
    public function __construct(
        private readonly IFinanceRepository $repository,
    ) {}

    public function execute(array $data): IncomeRecord
    {
        foreach (['source_type', 'reference_number', 'category', 'amount', 'currency', 'description'] as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new ValidationException([$field => ucfirst(str_replace('_', ' ', $field)) . ' is required.']);
            }
        }

        return $this->repository->createIncome($data);
    }
}
