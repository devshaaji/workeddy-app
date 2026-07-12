<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Content;

use PHPUnit\Framework\TestCase;

final class ContentAdminPagesScriptTest extends TestCase
{
    public function test_history_page_uses_api_driven_table_script(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/modules/Content/Presentation/Views/history.php');
        $script = file_get_contents($root . '/public/assets/js/modules/content-history.js');

        self::assertIsString($view);
        self::assertIsString($script);
        self::assertStringContainsString("pageScripts = ['js/modules/content-history.js'];", $view);
        self::assertStringContainsString('contentHistoryCard', $view);
        self::assertStringContainsString('contentHistoryBody', $view);
        self::assertStringContainsString('contentHistory-result-count', $view);
        self::assertStringNotContainsString('<?php foreach ($history as $entry): ?>', $view);
        self::assertStringContainsString('App.tables.createAdvanced', $script);
        self::assertStringContainsString('/api/v1/content/pages/', $script);
        self::assertStringContainsString('/revisions', $script);
        self::assertStringContainsString('contentHistoryBody', $script);
        self::assertStringContainsString('contentHistory-result-count', $script);
    }

    public function test_media_page_uses_standalone_filterable_js_table(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/modules/Content/Presentation/Views/media.php');
        $script = file_get_contents($root . '/public/assets/js/modules/content-media.js');

        self::assertIsString($view);
        self::assertIsString($script);
        self::assertStringContainsString("pageScripts = ['js/modules/content-media.js'];", $view);
        self::assertStringContainsString('contentMediaSearch', $view);
        self::assertStringContainsString('contentMediaMimeFilter', $view);
        self::assertStringContainsString('contentMediaBody', $view);
        self::assertStringNotContainsString('<?php foreach ($mediaItems as $item): ?>', $view);
        self::assertStringContainsString('App.tables.createAdvanced', $script);
        self::assertStringContainsString('/api/v1/content/media', $script);
        self::assertStringContainsString('contentMediaSearch', $script);
        self::assertStringContainsString('contentMediaMimeFilter', $script);
        self::assertStringContainsString('App.utils.debounce', $script);
    }
}
