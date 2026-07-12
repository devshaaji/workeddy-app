<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Content;

use PHPUnit\Framework\TestCase;

final class ContentEditorActionsPresentationTest extends TestCase
{
    public function test_editor_view_exposes_save_publish_restore_archive_controls(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/modules/Content/Presentation/Views/editor.php');
        $script = file_get_contents($root . '/public/assets/js/content-editor.js');
        $routes = file_get_contents($root . '/modules/Content/Presentation/routes.php');
        $controller = file_get_contents($root . '/modules/Content/Presentation/ContentPageController.php');

        self::assertIsString($view);
        self::assertIsString($script);
        self::assertIsString($routes);
        self::assertIsString($controller);

        self::assertStringContainsString('ceSaveDraft', $view);
        self::assertStringContainsString('cePublishBtn', $view);
        self::assertStringContainsString('ceOverflowArchive', $view);
        self::assertStringContainsString('ceInspectorHistory', $view);

        self::assertStringContainsString('pageApiBaseUrl', $script);
        self::assertStringContainsString('/draft', $script);
        self::assertStringContainsString('/publish', $script);
        self::assertStringContainsString('/revisions', $script);
        self::assertStringContainsString('window.App.api', $script);
        self::assertStringNotContainsString('fetch(', $script);
        self::assertStringNotContainsString('csrf-token', $script);
        self::assertStringNotContainsString('requestJson', $script);
        self::assertStringNotContainsString('postForm', $script);
        self::assertStringContainsString('setBusy', $script);
        self::assertStringContainsString('isBusy', $script);
        self::assertStringContainsString('409', $script);
        self::assertStringContainsString('lock version is stale', $script);

        self::assertStringContainsString('/pages/{pageUuid:', $routes);
        self::assertStringContainsString("[ContentPageController::class, 'edit']", $routes);
        self::assertStringContainsString('function edit(', $controller);
        self::assertStringContainsString('function methodology(', $controller);
        self::assertStringNotContainsString('function editor(', $controller);
    }
}
