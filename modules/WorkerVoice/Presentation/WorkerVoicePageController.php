<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Presentation;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\WorkerVoice\Authorization\WorkerVoicePermissions;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class WorkerVoicePageController
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly WorkerVoicePageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return $this->render('index.php', $request, $this->requirePrivilege(WorkerVoicePermissions::VIEW), 'Worker Feedback Register', ['pageScripts' => ['js/modules/worker-voice-index.js']]);
    }

    public function submit(Request $request): Response
    {
        return $this->render('submit.php', $request, $this->requirePrivilege(WorkerVoicePermissions::SUBMIT), 'Submit Worker Feedback', ['pageScripts' => ['js/modules/worker-voice-submit.js']]);
    }

    public function show(Request $request): Response
    {
        $ctx = $this->requirePrivilege(WorkerVoicePermissions::VIEW);

        return $this->render('show.php', $request, $ctx, 'Worker Feedback Detail', $this->pageData->show($ctx, (string) ($request->routeParams['feedbackId'] ?? '')));
    }

    public function trends(Request $request): Response
    {
        return $this->render('trends.php', $request, $this->requirePrivilege(WorkerVoicePermissions::VIEW_AGGREGATES), 'Worker Feedback Trends', ['pageScripts' => ['js/modules/worker-voice-trends.js']]);
    }

    public function supervisorSubmit(Request $request): Response
    {
        return $this->render('supervisor_submit.php', $request, $this->requirePrivilege(WorkerVoicePermissions::SUBMIT), 'Submit Supervisor Feedback', ['pageScripts' => ['js/modules/worker-voice-supervisor-submit.js']]);
    }

    public function supervisorTrends(Request $request): Response
    {
        return $this->render('supervisor_trends.php', $request, $this->requirePrivilege(WorkerVoicePermissions::VIEW_AGGREGATES), 'Supervisor Feedback Trends', ['pageScripts' => ['js/modules/worker-voice-supervisor-trends.js']]);
    }

    private function render(string $view, Request $request, UserContext $ctx, string $title, array $extra = []): Response
    {
        return $this->views->render(
            'modules/WorkerVoice/Presentation/Views/' . $view,
            'WorkerVoice',
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
