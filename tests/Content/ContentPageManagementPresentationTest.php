<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Content;

use PHPUnit\Framework\TestCase;

final class ContentPageManagementPresentationTest extends TestCase
{
    public function test_index_view_uses_page_header_and_table_card_pattern(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/modules/Content/Presentation/Views/index.php');

        self::assertIsString($view);
        self::assertStringContainsString("require \$v2Root . '/shared/Views/Partials/page_header.php';", $view);
        self::assertStringContainsString('contentPagesCard', $view);
        self::assertStringContainsString('<table class="table table-hover mb-0">', $view);
        self::assertStringContainsString('contentPagesBody', $view);
        self::assertStringContainsString('contentPages-result-count', $view);
    }

    public function test_create_view_uses_shared_form_patterns_without_inline_script(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/modules/Content/Presentation/Views/create.php');
        $script = file_get_contents($root . '/public/assets/js/modules/content-create.js');

        self::assertIsString($view);
        self::assertIsString($script);
        self::assertStringContainsString("require \$v2Root . '/shared/Views/Partials/page_header.php';", $view);
        self::assertStringContainsString('contentCreateForm', $view);
        self::assertStringContainsString('contentCreateSubmitBtn', $view);
        self::assertStringNotContainsString('<script>', $view);
        self::assertStringContainsString('App.forms.bindAjaxForm', $script);
        self::assertStringContainsString('/api/v1/content/pages', $script);
        self::assertStringNotContainsString('fetch(', $script);
    }
}
