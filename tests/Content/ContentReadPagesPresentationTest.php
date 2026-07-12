<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Content;

use PHPUnit\Framework\TestCase;

final class ContentReadPagesPresentationTest extends TestCase
{
    public function test_history_view_uses_page_header_and_revision_table_layout(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/modules/Content/Presentation/Views/history.php');

        self::assertIsString($view);
        self::assertStringContainsString("require \$v2Root . '/shared/Views/Partials/page_header.php';", $view);
        self::assertStringContainsString('contentHistoryPage', $view);
        self::assertStringContainsString('contentRevisionTable', $view);
        self::assertStringContainsString('contentHistoryBody', $view);
        self::assertStringContainsString('Loading revision history', $view);
    }

    public function test_preview_and_show_views_use_managed_page_layout(): void
    {
        $root = dirname(__DIR__, 2);
        $preview = file_get_contents($root . '/modules/Content/Presentation/Views/preview.php');
        $page = file_get_contents($root . '/modules/Content/Presentation/Views/page.php');
        $partial = file_get_contents($root . '/modules/Content/Presentation/Views/Partials/render_sections.php');

        self::assertIsString($preview);
        self::assertIsString($page);
        self::assertIsString($partial);
        self::assertStringContainsString("require \$v2Root . '/shared/Views/Partials/page_header.php';", $preview);
        self::assertStringContainsString("require \$v2Root . '/shared/Views/Partials/page_header.php';", $page);
        self::assertStringContainsString('contentManagedPage', $preview);
        self::assertStringContainsString('contentManagedPage', $page);
        self::assertStringContainsString('render_sections.php', $preview);
        self::assertStringContainsString('render_sections.php', $page);
        self::assertStringContainsString('content-section-card', $partial);
    }

    public function test_methodology_page_uses_dedicated_tree_navigation_layout(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/modules/Content/Presentation/Views/methodology.php');
        $css = file_get_contents($root . '/public/assets/css/methodology-page.css');
        $controller = file_get_contents($root . '/modules/Content/Presentation/ContentPageController.php');

        self::assertIsString($view);
        self::assertIsString($css);
        self::assertIsString($controller);
        self::assertStringContainsString("require \$v2Root . '/shared/Views/Partials/page_header.php';", $view);
        self::assertStringContainsString('$introSection = $contentSections[0] ?? null;', $view);
        self::assertStringContainsString('$closingSection = count($contentSections) > 1 ? $contentSections[array_key_last($contentSections)] : null;', $view);
        self::assertStringContainsString('methodology-tree-nav', $view);
        self::assertStringContainsString('methodology-tree-branches', $view);
        self::assertStringContainsString('methodology-tree-closing', $view);
        self::assertStringContainsString("render('modules/Content/Presentation/Views/methodology.php'", $controller);
        self::assertStringContainsString('.methodology-tree-shell', $css);
        self::assertStringContainsString('.methodology-tree-nav', $css);
        self::assertStringContainsString('.methodology-tree-branch', $css);
    }

    public function test_show_page_actions_are_permission_gated(): void
    {
        $root = dirname(__DIR__, 2);
        $page = file_get_contents($root . '/modules/Content/Presentation/Views/page.php');
        $controller = file_get_contents($root . '/modules/Content/Presentation/ContentPageController.php');

        self::assertIsString($page);
        self::assertIsString($controller);
        self::assertStringContainsString("if ((\$canEditPage ?? false) === true)", $page);
        self::assertStringContainsString("if ((\$canViewHistory ?? false) === true)", $page);
        self::assertStringContainsString("'canEditPage' => \$ctx->hasPermission(ContentPermissions::PAGES_UPDATE)", $controller);
        self::assertStringContainsString("'canViewHistory' => \$ctx->hasPermission(ContentPermissions::PAGES_READ)", $controller);
    }

    public function test_media_view_uses_admin_table_layout(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/modules/Content/Presentation/Views/media.php');

        self::assertIsString($view);
        self::assertStringContainsString("require \$v2Root . '/shared/Views/Partials/page_header.php';", $view);
        self::assertStringContainsString('contentMediaPage', $view);
        self::assertStringContainsString('contentMediaTable', $view);
        self::assertStringContainsString('Storage file', $view);
    }

    public function test_methodology_page_exists_as_a_dedicated_reader_surface(): void
    {
        $root = dirname(__DIR__, 2);
        $view = $root . '/modules/Content/Presentation/Views/methodology.php';

        self::assertFileExists($view);
    }
}
