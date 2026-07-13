<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Presentation;

use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class CorrectiveActionPageController
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
    ) {}
    public function recommendations(Request $request): Response
    {
        return $this->render(
            'recommendations.php',
            $request,
            $this->requirePrivilege(CorrectiveActionPermissions::REVIEW_RECOMMENDATIONS),
        );
    }

    public function controls(Request $request): Response
    {
        return $this->render(
            'controls.php',
            $request,
            $this->requirePrivilege(CorrectiveActionPermissions::MANAGE_LIBRARY),
        );
    }

    public function actions(Request $request): Response
    {
        return $this->render(
            'actions.php',
            $request,
            $this->requirePrivilege(CorrectiveActionPermissions::VIEW),
        );
    }

    public function show(Request $request): Response
    {
        return $this->render(
            'action_show.php',
            $request,
            $this->requirePrivilege(CorrectiveActionPermissions::VIEW),
        );
    }

    public function evidence(Request $request): Response
    {
        return $this->render(
            'evidence.php',
            $request,
            $this->requirePrivilege(CorrectiveActionPermissions::VIEW),
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

    private function render(string $view, Request $request, UserContext $ctx, array $extra = []): Response
    {
        return $this->views->render(
            'modules/CorrectiveAction/Presentation/Views/' . $view,
            'CorrectiveAction',
            array_replace(
                [
                    'routeParams' => $request->routeParams,
                    'query' => $request->query,
                ],
                $extra,
            ),
        );
    }
}
