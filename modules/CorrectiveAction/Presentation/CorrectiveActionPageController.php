<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Presentation;

use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveAction;
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
        unset($request);
        $ctx = $this->requirePrivilege(CorrectiveActionPermissions::REVIEW_RECOMMENDATIONS);
        return $this->render('recommendations.php', [$ctx]);
    }

    public function controls(Request $request): Response
    {
        unset($request);
        $ctx = $this->requirePrivilege(CorrectiveActionPermissions::MANAGE_LIBRARY);
        return $this->render('controls.php', [$ctx]);
    }

    public function actions(Request $request): Response
    {
        unset($request);
        $ctx = $this->requirePrivilege(CorrectiveActionPermissions::VIEW);
        return $this->render('actions.php', [$ctx]);
    }

    public function show(Request $request): Response
    {
        $ctx = $this->requirePrivilege(CorrectiveActionPermissions::VIEW);
        return $this->render('action_show.php', ['actionId' => $request->routeParam('actionId')]);
    }

    public function evidence(Request $request): Response
    {
        $ctx = $this->requirePrivilege(CorrectiveActionPermissions::VIEW);
        return $this->render('evidence.php', ['actionId' => $request->routeParam('actionId')]);
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

    private function render(string $view, array $vars): Response
    {
        return $this->views->render(
            'modules/CorrectiveAction/Presentation/Views/' . $view,
            'CorrectiveAction',
            [
                'routeParams' => $vars,
            ],
        );
    }
}
