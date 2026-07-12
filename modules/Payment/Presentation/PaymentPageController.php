<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Presentation;

use WorkEddy\Modules\Payment\Authorization\PaymentPermissions;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Shared\Presentation\ViewRenderer;
use WorkEddy\Shared\Exceptions\AuthenticationException;

final class PaymentPageController
{
    public function __construct(
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly ViewRenderer $views,
        private readonly PaymentPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        $vars = $request->routeParams;
        $ctx = $this->requirePrivilege(PaymentPermissions::VIEW_PAYMENTS);

        return $this->render('index.php', $vars, $this->pageData->index($ctx));
    }

    private function render(string $view, array $vars, array $data = []): Response
    {
        $ctx = $this->context();
        $data = array_replace($this->pageData->common($ctx), $data);

        return $this->views->render(
            'modules/Payment/Presentation/Views/' . $view,
            'Payment',
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
