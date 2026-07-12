<?php

declare(strict_types=1);

use WorkEddy\Modules\Finance\Authorization\FinancePermissions;
use WorkEddy\Modules\Finance\Presentation\Controllers\FinanceApiController;
use WorkEddy\Modules\Finance\Presentation\Controllers\FinancePageController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $uuidPattern = '[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[1-5][0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}';

    $routes->module('Finance', static function (RouteRegistrar $module) use ($uuidPattern): void {
        $module->group('/finance', static function (RouteRegistrar $web) use ($uuidPattern): void {
            $web->add('GET', '/dashboard', [FinancePageController::class, 'dashboard'], ['permission:' . FinancePermissions::VIEW]);
            $web->add('GET', '/income', [FinancePageController::class, 'income'], ['permission:' . FinancePermissions::VIEW]);
            $web->add('GET', '/income/new', [FinancePageController::class, 'createIncome'], ['permission:' . FinancePermissions::MANAGE]);
            $web->add('GET', '/income/{uuid:' . $uuidPattern . '}', [FinancePageController::class, 'showIncome'], ['permission:' . FinancePermissions::VIEW]);
            $web->add('GET', '/income/{uuid:' . $uuidPattern . '}/edit', [FinancePageController::class, 'editIncome'], ['permission:' . FinancePermissions::MANAGE]);
            $web->add('GET', '/expenses', [FinancePageController::class, 'expenses'], ['permission:' . FinancePermissions::VIEW]);
            $web->add('GET', '/expenses/new', [FinancePageController::class, 'createExpense'], ['permission:' . FinancePermissions::MANAGE]);
            $web->add('GET', '/expenses/{uuid:' . $uuidPattern . '}', [FinancePageController::class, 'showExpense'], ['permission:' . FinancePermissions::VIEW]);
            $web->add('GET', '/expenses/{uuid:' . $uuidPattern . '}/edit', [FinancePageController::class, 'editExpense'], ['permission:' . FinancePermissions::MANAGE]);
            $web->add('GET', '/payroll', [FinancePageController::class, 'payroll'], ['permission:' . FinancePermissions::VIEW]);
            $web->add('GET', '/settings', [FinancePageController::class, 'settings'], ['permission:' . FinancePermissions::SETTINGS]);
        }, ['auth']);

        $module->group('/api/v1/finance', static function (RouteRegistrar $api) use ($uuidPattern): void {
            $api->add('GET', '/dashboard', [FinanceApiController::class, 'dashboard'], ['permission:' . FinancePermissions::VIEW]);
            $api->add('GET', '/summary', [FinanceApiController::class, 'summary'], ['permission:' . FinancePermissions::VIEW]);
            $api->add('GET', '/income-records', [FinanceApiController::class, 'listIncome'], ['permission:' . FinancePermissions::VIEW]);
            $api->add('GET', '/expense-records', [FinanceApiController::class, 'listExpenses'], ['permission:' . FinancePermissions::VIEW]);
            $api->add('GET', '/payroll-summaries', [FinanceApiController::class, 'listPayrollSummaries'], ['permission:' . FinancePermissions::VIEW]);
            $api->add('POST', '/income-records', [FinanceApiController::class, 'createIncome'], ['permission:' . FinancePermissions::MANAGE]);
            $api->add('GET', '/income-records/{uuid:' . $uuidPattern . '}', [FinanceApiController::class, 'viewIncome'], ['permission:' . FinancePermissions::VIEW]);
            $api->add('PUT', '/income-records/{uuid:' . $uuidPattern . '}', [FinanceApiController::class, 'updateIncome'], ['permission:' . FinancePermissions::MANAGE]);
            $api->add('DELETE', '/income-records/{uuid:' . $uuidPattern . '}', [FinanceApiController::class, 'archiveIncome'], ['permission:' . FinancePermissions::MANAGE]);
            $api->add('POST', '/income-records/bulk-archive', [FinanceApiController::class, 'bulkArchiveIncome'], ['permission:' . FinancePermissions::MANAGE]);
            $api->add('POST', '/expense-records', [FinanceApiController::class, 'createExpense'], ['permission:' . FinancePermissions::MANAGE]);
            $api->add('GET', '/expense-records/{uuid:' . $uuidPattern . '}', [FinanceApiController::class, 'viewExpense'], ['permission:' . FinancePermissions::VIEW]);
            $api->add('PUT', '/expense-records/{uuid:' . $uuidPattern . '}', [FinanceApiController::class, 'updateExpense'], ['permission:' . FinancePermissions::MANAGE]);
            $api->add('DELETE', '/expense-records/{uuid:' . $uuidPattern . '}', [FinanceApiController::class, 'archiveExpense'], ['permission:' . FinancePermissions::MANAGE]);
            $api->add('POST', '/expense-records/bulk-archive', [FinanceApiController::class, 'bulkArchiveExpenses'], ['permission:' . FinancePermissions::MANAGE]);
            $api->add('POST', '/payroll-summaries/refresh', [FinanceApiController::class, 'refreshPayrollSummary'], ['permission:' . FinancePermissions::MANAGE]);
            $api->add('GET', '/settings', [FinanceApiController::class, 'getSettings'], ['permission:' . FinancePermissions::SETTINGS]);
            $api->add('PUT', '/settings', [FinanceApiController::class, 'updateSettings'], ['permission:' . FinancePermissions::SETTINGS]);
        }, ['auth']);
    });
};
