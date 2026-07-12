<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Presentation;

use WorkEddy\Modules\Notification\Authorization\NotificationPermissions;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class NotificationPageController
{
    public function __construct(
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly ViewRenderer $views,
        private readonly NotificationPageData $pageData,
    ) {}

    public function logs(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, NotificationPermissions::LOG_VIEW);
        return $this->render('Log/index.php', $request, $ctx, 'Notification Logs', ['pageScripts' => ['js/modules/notification.js']]);
    }

    public function templates(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, NotificationPermissions::TEMPLATE_VIEW);
        return $this->render('Template/index.php', $request, $ctx, 'Message Templates', ['pageScripts' => ['js/modules/notification.js']]);
    }

    public function settings(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, NotificationPermissions::SETTINGS_MANAGE);
        return $this->render('Settings/index.php', $request, $ctx, 'Notification Settings', ['pageScripts' => ['js/modules/notification.js']]);
    }

    private function render(string $view, Request $request, UserContext $ctx, string $title, array $extra = []): Response
    {
        return $this->views->render(
            'modules/Notification/Presentation/Views/' . $view,
            'Notification',
            array_replace($this->pageData->common($ctx, $title), ['routeParams' => $request->routeParams], $extra),
        );
    }

    private function requireContext(): UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated');
        }
        return $ctx;
    }
}
