<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance\Presentation;

use WorkEddy\Modules\Finance\Domain\Contracts\IFinanceRepository;
use WorkEddy\Modules\Finance\Settings\FinanceSettings;
use WorkEddy\Platform\Session\UserContext;

final class FinancePageData
{
    public function __construct(
        private readonly IFinanceRepository $repository,
        private readonly FinanceSettings $settings,
    ) {}

    public function dashboard(UserContext $ctx): array
    {
        return [
            'summary' => $this->repository->summary(),
            'income' => array_map(static fn($item): array => $item->toArray(), $this->repository->listIncome()),
            'expenses' => array_map(static fn($item): array => $item->toArray(), $this->repository->listExpenses()),
            'payroll_summaries' => array_map(static fn($item): array => $item->toArray(), $this->repository->listPayrollSummaries()),
            'defaults' => [
                'default_expense_currency' => $this->settings->defaultExpenseCurrency(),
                'payroll_summary_enabled' => $this->settings->payrollSummaryEnabled(),
            ],
            'user' => (string) $ctx->userId,
        ];
    }

    public function income(UserContext $ctx): array
    {
        return [
            'income' => array_map(static fn($item): array => $item->toArray(), $this->repository->listIncome()),
            'summary' => $this->repository->summary(),
            'defaults' => $this->defaults(),
            'user' => (string) $ctx->userId,
        ];
    }

    public function createIncome(UserContext $ctx): array
    {
        return [
            'record' => null,
            'defaults' => $this->defaults(),
            'user' => (string) $ctx->userId,
        ];
    }

    public function showIncome(string $uuid, UserContext $ctx): array
    {
        $record = $this->repository->findIncomeByUuid($uuid);

        return [
            'record' => $record?->toArray(),
            'defaults' => $this->defaults(),
            'user' => (string) $ctx->userId,
        ];
    }

    public function editIncome(string $uuid, UserContext $ctx): array
    {
        return $this->showIncome($uuid, $ctx);
    }

    public function expenses(UserContext $ctx): array
    {
        return [
            'expenses' => array_map(static fn($item): array => $item->toArray(), $this->repository->listExpenses()),
            'summary' => $this->repository->summary(),
            'defaults' => $this->defaults(),
            'user' => (string) $ctx->userId,
        ];
    }

    public function createExpense(UserContext $ctx): array
    {
        return [
            'record' => null,
            'defaults' => $this->defaults(),
            'user' => (string) $ctx->userId,
        ];
    }

    public function showExpense(string $uuid, UserContext $ctx): array
    {
        $record = $this->repository->findExpenseByUuid($uuid);

        return [
            'record' => $record?->toArray(),
            'defaults' => $this->defaults(),
            'user' => (string) $ctx->userId,
        ];
    }

    public function editExpense(string $uuid, UserContext $ctx): array
    {
        return $this->showExpense($uuid, $ctx);
    }

    public function payroll(UserContext $ctx): array
    {
        return [
            'payroll_summaries' => array_map(static fn($item): array => $item->toArray(), $this->repository->listPayrollSummaries()),
            'summary' => $this->repository->summary(),
            'defaults' => $this->defaults(),
            'user' => (string) $ctx->userId,
        ];
    }

    public function settings(UserContext $ctx): array
    {
        return [
            'defaults' => $this->defaults(),
            'user' => (string) $ctx->userId,
        ];
    }

    private function defaults(): array
    {
        return [
            'default_expense_currency' => $this->settings->defaultExpenseCurrency(),
            'payroll_summary_enabled' => $this->settings->payrollSummaryEnabled(),
        ];
    }
}
