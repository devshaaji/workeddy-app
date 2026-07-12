<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance\Presentation\Controllers;

use WorkEddy\Modules\Finance\Authorization\FinancePermissions;
use WorkEddy\Modules\Finance\Presentation\FinancePageData;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class FinancePageController
{
    public function __construct(
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly ViewRenderer $views,
        private readonly FinancePageData $pageData,
    ) {}

    public function dashboard(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, FinancePermissions::VIEW);

        return $this->views->render('modules/Finance/Presentation/Views/Index/dashboard.php', 'Finance', $this->pageData->dashboard($ctx));
    }

    public function income(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, FinancePermissions::VIEW);

        return $this->views->render('modules/Finance/Presentation/Views/Income/index.php', 'Finance', $this->pageData->income($ctx));
    }

    public function createIncome(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, FinancePermissions::MANAGE);

        return $this->views->render('modules/Finance/Presentation/Views/Income/form.php', 'Finance', array_replace($this->pageData->createIncome($ctx), [
            'mode' => 'create',
        ]));
    }

    public function showIncome(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, FinancePermissions::VIEW);

        return $this->views->render('modules/Finance/Presentation/Views/Income/show.php', 'Finance', $this->pageData->showIncome((string) $request->routeParam('uuid'), $ctx));
    }

    public function editIncome(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, FinancePermissions::MANAGE);

        return $this->views->render('modules/Finance/Presentation/Views/Income/form.php', 'Finance', array_replace($this->pageData->editIncome((string) $request->routeParam('uuid'), $ctx), [
            'mode' => 'edit',
        ]));
    }

    public function expenses(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, FinancePermissions::VIEW);

        return $this->views->render('modules/Finance/Presentation/Views/Index/expenses.php', 'Finance', $this->pageData->expenses($ctx));
    }

    public function createExpense(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, FinancePermissions::MANAGE);

        return $this->views->render('modules/Finance/Presentation/Views/Expense/form.php', 'Finance', array_replace($this->pageData->createExpense($ctx), [
            'mode' => 'create',
        ]));
    }

    public function showExpense(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, FinancePermissions::VIEW);

        return $this->views->render('modules/Finance/Presentation/Views/Expense/show.php', 'Finance', $this->pageData->showExpense((string) $request->routeParam('uuid'), $ctx));
    }

    public function editExpense(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, FinancePermissions::MANAGE);

        return $this->views->render('modules/Finance/Presentation/Views/Expense/form.php', 'Finance', array_replace($this->pageData->editExpense((string) $request->routeParam('uuid'), $ctx), [
            'mode' => 'edit',
        ]));
    }

    public function payroll(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, FinancePermissions::VIEW);

        return $this->views->render('modules/Finance/Presentation/Views/Index/payroll.php', 'Finance', $this->pageData->payroll($ctx));
    }

    public function settings(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, FinancePermissions::SETTINGS);

        return $this->views->render('modules/Finance/Presentation/Views/Settings/index.php', 'Finance', $this->pageData->settings($ctx));
    }

    private function requireContext(): \WorkEddy\Platform\Session\UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $ctx;
    }
}
