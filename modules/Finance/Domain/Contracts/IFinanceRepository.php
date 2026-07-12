<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance\Domain\Contracts;

use WorkEddy\Modules\Finance\Domain\Entities\ExpenseRecord;
use WorkEddy\Modules\Finance\Domain\Entities\IncomeRecord;
use WorkEddy\Modules\Finance\Domain\Entities\PayrollSummary;

interface IFinanceRepository
{
    public function createIncome(array $data): IncomeRecord;

    public function findIncomeByUuid(string $uuid): ?IncomeRecord;

    public function updateIncome(int $id, array $data): void;

    public function archiveIncome(int $id): void;

    public function createExpense(array $data): ExpenseRecord;

    public function findExpenseByUuid(string $uuid): ?ExpenseRecord;

    public function updateExpense(int $id, array $data): void;

    public function archiveExpense(int $id): void;

    public function upsertPayrollSummary(array $data): PayrollSummary;

    public function listIncome(array $filters = []): array;

    public function listExpenses(array $filters = []): array;

    public function listPayrollSummaries(array $filters = []): array;

    public function summary(): array;
}
