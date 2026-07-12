<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Shared;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Content\Authorization\ContentPermissions;
use WorkEddy\Modules\WorkerVoice\Authorization\WorkerVoicePermissions;
use WorkEddy\Platform\Session\UserContext;

final class SidebarActiveStateTest extends TestCase
{
    public function testAssessmentDetailPathActivatesAssessmentsMenu(): void
    {
        $html = $this->renderSidebar(
            currentView: 'modules/Assessment/Presentation/Views/show.php',
            requestUri: '/assessments/11111111-1111-4111-8111-111111111111',
            userContext: new UserContext(userId: 42)
        );

        self::assertMatchesRegularExpression(
            '/<li class="menu-item active open">\s*<a href="javascript:void\(0\);" class="menu-link menu-toggle">\s*<i class="menu-icon (icon-base\s+)?bi bi-clipboard-pulse"><\/i>\s*<div data-i18n="Assessments">Assessments<\/div>/s',
            $html
        );
        self::assertMatchesRegularExpression(
            '/<li class="menu-item active">\s*<a class="menu-link" href="\/assessments">\s*All Assessments/s',
            $html
        );
    }

    public function testWorkerVoiceRoutePathActivatesSupervisorTrendsItem(): void
    {
        $html = $this->renderSidebar(
            currentView: 'modules/WorkerVoice/Presentation/Views/supervisor_trends.php',
            requestUri: '/worker-voice/supervisor/trends',
            userContext: new UserContext(
                userId: 42,
                permissions: [WorkerVoicePermissions::VIEW_AGGREGATES]
            )
        );

        self::assertMatchesRegularExpression(
            '/<li class="menu-item active open">\s*<a href="javascript:void\(0\);" class="menu-link menu-toggle">\s*<i class="menu-icon (icon-base\s+)?bi bi-chat-square-text"><\/i>\s*<div data-i18n="Worker Voice">Worker Voice<\/div>/s',
            $html
        );
        self::assertMatchesRegularExpression(
            '/<li class="menu-item active">\s*<a class="menu-link" href="\/worker-voice\/supervisor\/trends">\s*Supervisor Trends/s',
            $html
        );
    }

    public function testMethodologyLinkRendersOutsidePlatformForContentReaders(): void
    {
        $platformHtml = $this->renderSidebar(
            currentView: 'modules/Content/Presentation/Views/index.php',
            requestUri: '/content',
            userContext: new UserContext(
                userId: 42,
                permissions: [ContentPermissions::PAGES_READ, ContentPermissions::MEDIA_READ]
            )
        );

        self::assertMatchesRegularExpression(
            '/<li class="menu-header small text-uppercase text-muted fw-semibold">Platform<\/li>/s',
            $platformHtml
        );
        self::assertMatchesRegularExpression(
            '/<li class="menu-item">\s*<a class="menu-link" href="\/content\/methodology-and-limitations">\s*<i class="menu-icon (icon-base\s+)?bi bi-journal-text"><\/i>\s*<div data-i18n="Methodology">Methodology<\/div>/s',
            $platformHtml
        );
        self::assertStringContainsString('href="/content/media"', $platformHtml);
        self::assertStringContainsString('href="/content"', $platformHtml);

        $orgHtml = $this->renderSidebar(
            currentView: 'modules/Organization/Presentation/Views/index.php',
            requestUri: '/organizations/org-1',
            userContext: new UserContext(
                userId: 77,
                organizationUuid: 'org-1',
                permissions: ['organization.members.manage']
            )
        );

        self::assertStringNotContainsString('href="/content"', $orgHtml);
        self::assertStringNotContainsString('data-i18n="Content"', $orgHtml);
        self::assertStringNotContainsString('data-i18n="Methodology"', $orgHtml);
    }

    public function testMethodologyRouteActivatesDedicatedSidebarItem(): void
    {
        $html = $this->renderSidebar(
            currentView: 'modules/Content/Presentation/Views/page.php',
            requestUri: '/content/methodology-and-limitations',
            userContext: new UserContext(
                userId: 42,
                permissions: [ContentPermissions::PAGES_READ]
            )
        );

        self::assertMatchesRegularExpression(
            '/<li class="menu-item active">\s*<a class="menu-link" href="\/content\/methodology-and-limitations">\s*<i class="menu-icon (icon-base\s+)?bi bi-journal-text"><\/i>\s*<div data-i18n="Methodology">Methodology<\/div>/s',
            $html
        );
    }

    private function renderSidebar(string $currentView, string $requestUri, UserContext $userContext): string
    {
        $root = dirname(__DIR__, 2);
        $previousRequestUri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = $requestUri;

        $currentUserContext = $userContext;
        $activePage = null;

        ob_start();
        require $root . '/shared/Views/Partials/sidebar.php';
        $html = (string) ob_get_clean();

        if ($previousRequestUri === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $previousRequestUri;
        }

        return $html;
    }
}
