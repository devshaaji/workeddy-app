<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Presentation;

use WorkEddy\Modules\Billing\Authorization\BillingPermissions;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Shared\Presentation\ViewRenderer;
use WorkEddy\Shared\Exceptions\AuthenticationException;

final class BillingPageController
{
    public function __construct(
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly ViewRenderer $views,
        private readonly BillingPageData $pageData,
    ) {}

    public function quotations(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->requirePrivilege(BillingPermissions::VIEW_BILLING);

        return $this->render('quotations.php', $vars, $this->pageData->quotations($ctx));
    }

    public function createQuotation(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->requirePrivilege(BillingPermissions::MANAGE_QUOTATIONS);

        return $this->render('quotation_form.php', $vars, $this->pageData->createQuotation($ctx));
    }

    public function quotationDetail(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->requirePrivilege(BillingPermissions::VIEW_BILLING);

        return $this->render('quotation_detail.php', $vars, $this->pageData->quotationDetail($ctx, (string) ($vars['uuid'] ?? '')));
    }

    public function invoices(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->requirePrivilege(BillingPermissions::VIEW_BILLING);

        return $this->render('invoices.php', $vars, $this->pageData->invoices($ctx));
    }

    public function createInvoice(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->requirePrivilege(BillingPermissions::MANAGE_INVOICES);

        return $this->render('invoice_form.php', $vars, $this->pageData->createInvoice($ctx));
    }

    public function invoiceDetail(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->requirePrivilege(BillingPermissions::VIEW_BILLING);

        return $this->render('invoice_detail.php', $vars, $this->pageData->invoiceDetail($ctx, (string) ($vars['uuid'] ?? '')));
    }

    public function settings(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->requirePrivilege(BillingPermissions::VIEW_BILLING);

        return $this->render('settings.php', $vars, $this->pageData->settings($ctx));
    }

    private function render(string $view, array $vars, array $data = []): Response
    {
        $ctx = $this->context();
        $data = array_replace($this->pageData->common($ctx), $data);

        return $this->views->render(
            'modules/Billing/Presentation/Views/' . $view,
            'Billing',
            array_replace(['routeParams' => $vars], $data),
        );
    }

    private function requirePrivilege(string $privilege): UserContext
    {
        $ctx = $this->context();
        $this->permissions->requirePrivilege($ctx, $privilege);

        return $ctx;
    }

    private function context(): UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }
}
