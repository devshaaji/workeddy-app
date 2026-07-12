<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Task\Presentation;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Task\Authorization\TaskPermissions;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class TaskPageController
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly TaskPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return $this->render('index.php', $request, $this->requirePrivilege(TaskPermissions::VIEW), 'Tasks', ['pageScripts' => ['js/modules/task-index.js']]);
    }

    public function show(Request $request): Response
    {
        return $this->render('show.php', $request, $this->requirePrivilege(TaskPermissions::VIEW), 'Task Detail', ['pageScripts' => ['js/modules/task-show.js']]);
    }

    public function edit(Request $request): Response
    {
        $ctx = $this->requirePrivilege(TaskPermissions::UPDATE);
        $taskId = (string) ($request->routeParam('taskId') ?? '');

        return Response::redirect('/tasks?edit=' . rawurlencode($taskId));
    }

    private function render(string $view, Request $request, UserContext $ctx, string $title, array $extra = []): Response
    {
        return $this->views->render(
            'modules/Task/Presentation/Views/' . $view,
            'Task',
            array_replace($this->pageData->common($ctx, $title), ['routeParams' => $request->routeParams, 'query' => $request->query], $extra),
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
