<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Presentation;

use WorkEddy\Modules\Content\Application\Services\ContentWorkflowService;
use WorkEddy\Modules\Content\Application\Services\ContentQueryService;
use WorkEddy\Modules\Content\Authorization\ContentPermissions;
use WorkEddy\Modules\Content\Domain\Contracts\IContentMediaRepository;
use WorkEddy\Modules\Content\Support\MethodologyPageDefinition;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class ContentPageController
{
    public function __construct(
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly ViewRenderer $views,
        private readonly ContentQueryService $pages,
        private readonly ContentWorkflowService $workflow,
        private readonly ?IContentMediaRepository $media = null,
    ) {}

    public function index(Request $request): Response
    {
        unset($request);
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_READ);

        return $this->views->render('modules/Content/Presentation/Views/index.php', 'Content Pages', [
            'pages' => $this->pages->listPages(),
        ]);
    }

    public function create(Request $request): Response
    {
        unset($request);
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_CREATE);

        return $this->views->render('modules/Content/Presentation/Views/create.php', 'Create Content Page', []);
    }

    public function show(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_READ);
        $pageUuid = (string) $request->routeParam('pageUuid');
        $summary = $this->requirePageSummary($pageUuid);
        $page = $this->pages->findPublishedByKey((string) $summary['pageKey']);

        return $this->views->render('modules/Content/Presentation/Views/page.php', (string) ($summary['title'] ?? 'Content Page'), [
            'page' => $page,
            'summary' => $summary,
            'message' => $page === null ? 'This content page has not been published yet.' : null,
            'canEditPage' => $ctx->hasPermission(ContentPermissions::PAGES_UPDATE),
            'canViewHistory' => $ctx->hasPermission(ContentPermissions::PAGES_READ),
        ]);
    }

    public function showByKey(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_READ);
        $pageKey = (string) $request->routeParam('pageKey');
        $summary = $this->pages->findPageSummaryByKey($pageKey)
            ?? throw new \RuntimeException('Content page "' . $pageKey . '" was not found.');
        $page = $this->pages->findPublishedByKey($pageKey);

        return $this->views->render('modules/Content/Presentation/Views/page.php', (string) ($summary['title'] ?? 'Content Page'), [
            'page' => $page,
            'summary' => $summary,
            'message' => $page === null ? 'This content page has not been published yet.' : null,
            'canEditPage' => $ctx->hasPermission(ContentPermissions::PAGES_UPDATE),
            'canViewHistory' => $ctx->hasPermission(ContentPermissions::PAGES_READ),
        ]);
    }

    public function methodology(Request $request): Response
    {
        unset($request);
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_READ);
        $summary = $this->pages->findPageSummaryByKey(MethodologyPageDefinition::PAGE_KEY)
            ?? throw new \RuntimeException('Content page "' . MethodologyPageDefinition::PAGE_KEY . '" was not found.');
        $page = $this->pages->findPublishedByKey(MethodologyPageDefinition::PAGE_KEY);

        return $this->views->render('modules/Content/Presentation/Views/methodology.php', (string) ($summary['title'] ?? 'Methodology and Limitations'), [
            'page' => $page,
            'summary' => $summary,
            'message' => $page === null ? 'This content page has not been published yet.' : null,
            'canEditPage' => $ctx->hasPermission(ContentPermissions::PAGES_UPDATE),
            'canViewHistory' => $ctx->hasPermission(ContentPermissions::PAGES_READ),
        ]);
    }

    public function edit(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_UPDATE);
        $pageUuid = (string) $request->routeParam('pageUuid');
        $summary = $this->requirePageSummary($pageUuid);
        $draft = $this->pages->findDraftByUuid($pageUuid);
        if ($draft === null && !empty($summary['publishedRevisionUuid'])) {
            $this->workflow->beginDraftFromPublished((string) $summary['pageKey'], (int) $ctx->userId, 'Editor draft started');
            $summary = $this->requirePageSummary($pageUuid);
            $draft = $this->pages->findDraftByUuid($pageUuid);
        }

        return $this->views->render('modules/Content/Presentation/Views/editor.php', 'Content Editor', [
            'page' => $summary,
            'draft' => $draft,
            'history' => $this->pages->listRevisionHistoryByPageUuid($pageUuid),
        ]);
    }

    public function preview(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PREVIEW);
        $pageUuid = (string) $request->routeParam('pageUuid');
        $draft = $this->pages->findDraftByUuid($pageUuid);
        $summary = $this->requirePageSummary($pageUuid);

        return $this->views->render('modules/Content/Presentation/Views/preview.php', 'Content Preview', [
            'summary' => $summary,
            'preview' => $draft,
        ]);
    }

    public function history(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_READ);
        $pageUuid = (string) $request->routeParam('pageUuid');

        return $this->views->render('modules/Content/Presentation/Views/history.php', 'Revision History', [
            'page' => $this->requirePageSummary($pageUuid),
            'history' => $this->pages->listRevisionHistoryByPageUuid($pageUuid),
        ]);
    }

    public function revision(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_READ);
        $pageUuid = (string) $request->routeParam('pageUuid');
        $revisionUuid = (string) $request->routeParam('revisionUuid');

        return $this->views->render('modules/Content/Presentation/Views/preview.php', 'Revision Preview', [
            'summary' => $this->requirePageSummary($pageUuid),
            'preview' => $this->pages->findRevisionForPage($pageUuid, $revisionUuid),
        ]);
    }

    public function media(Request $request): Response
    {
        unset($request);
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::MEDIA_READ);

        return $this->views->render('modules/Content/Presentation/Views/media.php', 'Content Media', [
            'mediaItems' => $this->media?->listSelectable() ?? [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requirePageSummary(string $pageUuid): array
    {
        return $this->pages->findPageSummaryByUuid($pageUuid)
            ?? throw new \RuntimeException('Content page "' . $pageUuid . '" was not found.');
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
