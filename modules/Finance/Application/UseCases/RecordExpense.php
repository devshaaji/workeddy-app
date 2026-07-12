<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance\Application\UseCases;

use WorkEddy\Modules\Finance\Domain\Contracts\IFinanceRepository;
use WorkEddy\Modules\Finance\Domain\Entities\ExpenseRecord;
use WorkEddy\Modules\Finance\Settings\FinanceSettings;
use WorkEddy\Shared\Exceptions\ValidationException;

final class RecordExpense
{
    public function __construct(
        private readonly IFinanceRepository $repository,
        private readonly FinanceSettings $settings,
    ) {}

    public function execute(array $data): ExpenseRecord
    {
        foreach (['reference_number', 'category', 'amount', 'description'] as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new ValidationException([$field => ucfirst(str_replace('_', ' ', $field)) . ' is required.']);
            }
        }

        $data['currency'] ??= $this->settings->defaultExpenseCurrency();

        return $this->repository->createExpense($data);
    }
}
