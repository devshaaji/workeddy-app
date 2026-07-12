<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Content;

use PHPUnit\Framework\TestCase;

final class ContentEditorPresentationTest extends TestCase
{
    public function test_editor_view_uses_quill_and_structured_section_workspace(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/modules/Content/Presentation/Views/editor.php');

        self::assertIsString($view);
        self::assertStringContainsString('contentEditorApp', $view);
        self::assertStringContainsString('quill', strtolower($view));
        self::assertStringContainsString('ceSectionList', $view);
        self::assertStringContainsString('ceWorkspaceContent', $view);
        self::assertStringContainsString('ceInspector', $view);
        self::assertStringContainsString('ceQuillToolbar', $view);
        self::assertStringContainsString('ceQuillEditor', $view);
        self::assertStringContainsString('type="application/json"', $view);
        self::assertStringNotContainsString('<script src=', $view);
    }
}
