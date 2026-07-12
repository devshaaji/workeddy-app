<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance\Application\UseCases;

use WorkEddy\Modules\Finance\Domain\Contracts\IFinanceRepository;
use WorkEddy\Modules\Finance\Domain\Entities\PayrollSummary;
use WorkEddy\Modules\Finance\Settings\FinanceSettings;
use Doctrine\DBAL\Connection;

final class RefreshPayrollSummary
{
    public function __construct(
        private readonly IFinanceRepository $repository,
        private readonly Connection $connection,
        private readonly FinanceSettings $settings,
    ) {}

    public function execute(string $periodKey): PayrollSummary
    {
        if (!$this->settings->payrollSummaryEnabled()) {
            return $this->repository->upsertPayrollSummary([
                'period_key' => $periodKey,
                'gross_amount' => 0,
                'net_amount' => 0,
                'currency' => $this->settings->defaultExpenseCurrency(),
                'employee_count' => 0,
            ]);
        }

        $row = $this->connection->fetchAssociative(
            'SELECT COALESCE(SUM(gross_amount), 0) AS gross_total, COALESCE(SUM(net_amount), 0) AS net_total, COALESCE(MAX(currency), ?) AS currency, COUNT(*) AS employee_count FROM hrm_salary_records WHERE period_key = ? AND deleted_at IS NULL',
            [$this->settings->defaultExpenseCurrency(), $periodKey]
        );

        return $this->repository->upsertPayrollSummary([
            'period_key' => $periodKey,
            'gross_amount' => (float) ($row['gross_total'] ?? 0),
            'net_amount' => (float) ($row['net_total'] ?? 0),
            'currency' => (string) ($row['currency'] ?? $this->settings->defaultExpenseCurrency()),
            'employee_count' => (int) ($row['employee_count'] ?? 0),
        ]);
    }
}
