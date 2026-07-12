<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class FinanceSettings extends ModuleSettings
{
    public const DEFAULT_EXPENSE_CURRENCY = 'default_expense_currency';
    public const PAYROLL_SUMMARY_ENABLED = 'payroll_summary_enabled';

    protected function moduleName(): string
    {
        return 'finance';
    }

    public function defaultExpenseCurrency(): string
    {
        return $this->getString(self::DEFAULT_EXPENSE_CURRENCY);
    }

    public function payrollSummaryEnabled(): bool
    {
        return $this->getBool(self::PAYROLL_SUMMARY_ENABLED);
    }
}
